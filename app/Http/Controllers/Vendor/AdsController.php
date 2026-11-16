<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\BusinessRule;
use App\Models\Product;
use App\Services\Ads\AdApprovalService;
use App\Services\Payments\MobileMoney;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AdsController extends Controller
{
    public function __construct(
        protected AdApprovalService $approval,
        protected MobileMoney $mobileMoney
    ) {}

    public function index()
    {
        $vendor = Auth::guard('vendor')->user();

        $products = Product::where('vendor_id', $vendor->id)
            ->with(['translation', 'primaryVariant'])
            ->orderBy('id', 'desc')
            ->get();

        $campaigns = AdCampaign::with('product.translation')
            ->where('vendor_id', $vendor->id)
            ->orderBy('id', 'desc')
            ->get();

        $minBudget = \App\Support\Ads\AdBudget::minimum();

        return view('vendor.ads.index', compact('products', 'campaigns', 'minBudget'));
    }

    public function store(Request $request)
    {
        $vendor = Auth::guard('vendor')->user();

        $minBudget = \App\Support\Ads\AdBudget::minimum();

        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'title' => 'required|string|max:150',
            'total_budget' => $minBudget > 0
                ? ['required', 'numeric', 'min:'.$minBudget]
                : ['required', 'numeric', 'min:1'],
            'days' => 'required|integer|min:1|max:90',
            'payment_method' => 'required|string|max:50',
            'mobile_money_provider' => 'nullable|string|max:50',
            'mobile_money_number' => 'nullable|string|max:20',
            'placement_id' => 'nullable|exists:ad_placements,id',
            'package_id' => 'nullable|exists:ad_packages,id',
            'objective' => 'nullable|in:awareness,traffic,conversions',
            'billing_model' => 'nullable|in:cpm,cpc,flat',
            'creative_headline' => 'nullable|string|max:190',
            'creative_body' => 'nullable|string|max:1000',
            'creative_image' => 'nullable|string|max:255',
            'target_url' => 'nullable|url|max:255',
        ]);

        // A vendor never declares a campaign as paid. Mobile money details are
        // validated and stored so an admin can reconcile the collection, and
        // when the aggregator is live the charge is started right away.
        $mobile = null;
        $declared = ! empty($data['mobile_money_provider']) || ! empty($data['mobile_money_number']);

        if ($data['payment_method'] === MobileMoney::GATEWAY_CODE
            && ($this->mobileMoney->isConfigured() || $declared)) {
            try {
                $mobile = $this->mobileMoney->validate(
                    $data['mobile_money_provider'] ?? null,
                    $data['mobile_money_number'] ?? null
                );
            } catch (ValidationException $e) {
                return back()->withErrors($e->errors())->withInput();
            }
        }

        $product = Product::where('id', $data['product_id'])->where('vendor_id', $vendor->id)->firstOrFail();

        $dailyBudget = round($data['total_budget'] / max(1, (int) $data['days']), 2);
        $placement = isset($data['placement_id']) ? \App\Models\AdPlacement::find($data['placement_id']) : null;
        $bidMinor = $placement
            ? ((int) $placement->min_bid_minor ?: \App\Support\Ads\Money::minor($placement->base_price))
            : 0;

        $campaign = AdCampaign::create([
            'vendor_id' => $vendor->id,
            'product_id' => $product->id,
            'placement_id' => $data['placement_id'] ?? null,
            'package_id' => $data['package_id'] ?? null,
            'title' => $data['title'],
            'objective' => $data['objective'] ?? 'awareness',
            'billing_model' => $data['billing_model'] ?? BusinessRule::get('ad_default_billing_model', 'cpm'),
            'currency' => \App\Support\Ads\Money::DEFAULT_CURRENCY,
            'bid_type' => 'manual_cpc',
            'bid_minor' => $bidMinor,
            'quality_factor' => 1.0,
            'total_budget_minor' => \App\Support\Ads\Money::minor($data['total_budget']),
            'daily_budget_minor' => \App\Support\Ads\Money::minor($dailyBudget),
            'amount_paid_minor' => 0,
            'spent_minor' => 0,
            'reserved_minor' => 0,
            'pacing' => 'even',
            'creative_headline' => $data['creative_headline'] ?? null,
            'creative_body' => $data['creative_body'] ?? null,
            'creative_image' => $data['creative_image'] ?? null,
            'target_url' => $data['target_url'] ?? null,
            'total_budget' => $data['total_budget'],
            'daily_budget' => $dailyBudget,
            'amount_paid' => 0,
            'payment_method' => $data['payment_method'],
            'payment_meta' => $mobile !== null ? [
                'provider' => $mobile['provider'],
                'msisdn' => $mobile['msisdn'],
            ] : null,
            'paid_at' => null,
            'start_date' => today(),
            'end_date' => today()->addDays((int) $data['days']),
            'status' => 'draft',
        ]);

        if ($mobile !== null && $this->mobileMoney->isConfigured()) {
            $reference = 'AD-'.$campaign->id;

            try {
                $charge = $this->mobileMoney->requestPayment(
                    $mobile['provider'],
                    $mobile['msisdn'],
                    (float) $campaign->total_budget,
                    ['reference' => $reference]
                );
            } catch (ValidationException $e) {
                // Nothing was submitted yet, so the half-created campaign is
                // removed instead of leaving an unpaid campaign behind.
                $campaign->delete();

                return back()->withErrors($e->errors())->withInput();
            }

            $campaign->update([
                'payment_meta' => array_merge($campaign->payment_meta ?? [], [
                    'reference' => $charge['reference'] ?? null,
                    'merchant_reference' => $reference,
                    'status' => $charge['status'] ?? 'pending',
                    'message' => $charge['message'] ?? null,
                ]),
            ]);
        }

        $this->approval->submit($campaign);
        $status = $campaign->fresh()->status;
        $approved = in_array($status, ['active', 'scheduled'], true);

        if ($approved) {
            $message = 'Your ad was approved and is now live.';
        } elseif ($status === 'awaiting_payment') {
            $message = 'Campaign submitted. Your ad will go live once the payment of '
                .number_format((float) $campaign->total_budget).' is confirmed.';
        } else {
            $message = 'Campaign submitted and pending review.';
        }

        return redirect()->route('vendor.ads.index')->with('success', $message);
    }

    public function pause(AdCampaign $campaign)
    {
        $this->authorizeVendor($campaign);

        if (in_array($campaign->status, ['active', 'scheduled', 'approved'], true)) {
            $this->approval->pause($campaign);
        }

        return redirect()->route('vendor.ads.index')->with('success', 'Campaign paused.');
    }

    public function resume(AdCampaign $campaign)
    {
        $this->authorizeVendor($campaign);

        if ($campaign->status === 'paused') {
            $this->approval->resume($campaign);
        }

        return redirect()->route('vendor.ads.index')->with('success', 'Campaign resumed.');
    }

    public function destroy(AdCampaign $campaign)
    {
        $this->authorizeVendor($campaign);
        $this->approval->unsyncProduct($campaign);
        $campaign->delete();

        return redirect()->route('vendor.ads.index')->with('success', 'Campaign removed.');
    }

    protected function authorizeVendor(AdCampaign $campaign): void
    {
        if ($campaign->vendor_id !== Auth::guard('vendor')->id()) {
            abort(403);
        }
    }
}
