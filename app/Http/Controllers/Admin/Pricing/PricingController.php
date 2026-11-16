<?php

namespace App\Http\Controllers\Admin\Pricing;

use App\Http\Controllers\Controller;
use App\Models\PriceChangeLog;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Vendor;
use App\Services\Admin\AuditLogger;
use App\Services\Pricing\MarginGuardrail;
use App\Services\Pricing\PricingRecommendationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * Admin pricing & promotion console. Read-heavy oversight plus the few governed
 * mutations: create a funded promotion, move it through its lifecycle, and
 * override a margin guardrail (which is always audited).
 */
class PricingController extends Controller
{
    public function __construct(
        protected MarginGuardrail $guardrails,
        protected PricingRecommendationService $recommendations,
    ) {}

    public function index(Request $request)
    {
        $status = $request->input('status');
        $vendorId = $request->input('vendor_id');

        $promotions = Promotion::query()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($vendorId, fn ($q) => $q->where('vendor_id', $vendorId))
            ->with(['product.translation', 'vendor'])
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $exposure = $promotions->getCollection()->mapWithKeys(
            fn (Promotion $p) => [$p->id => $this->recommendations->exposure($p)]
        );

        return view('admin.pricing.index', [
            'promotions' => $promotions,
            'exposure' => $exposure,
            'vendors' => Vendor::orderBy('name')->limit(200)->get(['id', 'name']),
            'status' => $status,
            'vendorId' => $vendorId,
            'history' => PriceChangeLog::with('product.translation')
                ->orderByDesc('id')
                ->limit(30)
                ->get(),
            'guardrails' => [
                'max_discount_percent' => (float) \App\Models\BusinessRule::number('pricing.max_discount_percent', 0),
                'min_price' => (float) \App\Models\BusinessRule::number('pricing.min_price', 0),
                'stack_mode' => \App\Models\BusinessRule::get('pricing.stack_mode', 'best_per_group'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = Validator::make($request->all(), [
            'title' => 'required|string|max:150',
            'type' => 'required|in:percent,fixed',
            'value' => 'required|numeric|min:0.01',
            'scope' => 'required|in:order,vendor,category',
            'vendor_id' => 'nullable|exists:vendors,id',
            'category_id' => 'nullable|integer',
            'funding_source' => 'required|in:vendor,juvo,joint',
            'stack_group' => 'nullable|string|max:40',
            'priority' => 'nullable|integer|min:0|max:100',
            'budget_amount' => 'nullable|numeric|min:0',
            'per_order_cap' => 'nullable|numeric|min:0',
            'coupon_compatible' => 'nullable|boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'require_approval' => 'nullable|boolean',
        ])->validate();

        $funded = in_array($data['funding_source'], [Promotion::FUNDING_JUVO, Promotion::FUNDING_JOINT], true);
        $status = ($data['require_approval'] ?? false) ? 'pending' : 'active';
        if ($funded && ! Auth::user()->can('pricing.approve')) {
            $status = 'pending';
        }

        $promotion = Promotion::create([
            'vendor_id' => $data['vendor_id'] ?? null,
            'product_id' => null,
            'category_id' => $data['category_id'] ?? null,
            'title' => $data['title'],
            'type' => $data['type'],
            'value' => $data['value'],
            'active' => $status === 'active',
            'status' => $status,
            'scope' => $data['scope'],
            'funding_source' => $data['funding_source'],
            'stack_group' => $data['stack_group'] ?? null,
            'priority' => $data['priority'] ?? 0,
            'budget_amount' => $data['budget_amount'] ?? null,
            'per_order_cap' => $data['per_order_cap'] ?? null,
            'coupon_compatible' => $request->boolean('coupon_compatible'),
            'created_by' => Auth::id(),
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
        ]);

        AuditLogger::record('pricing.promotion.created', $promotion, [], [
            'title' => $promotion->title,
            'funding_source' => $promotion->funding_source,
            'status' => $promotion->status,
        ]);

        return back()->with('success', $status === 'active'
            ? 'Promotion created and active.'
            : 'Promotion created and queued for approval.');
    }

    public function updateStatus(Request $request, Promotion $promotion)
    {
        $data = Validator::make($request->all(), [
            'status' => 'required|in:active,paused,expired,archived,pending,rejected,approved',
            'reason' => 'nullable|string|max:500',
        ])->validate();

        $target = $data['status'];
        if ($target === 'approved') {
            $target = 'active';
        }
        if ($target === 'rejected' && ! Auth::user()->can('pricing.approve')) {
            abort(403);
        }

        $before = ['status' => $promotion->status];
        $promotion->update([
            'status' => $target,
            'active' => $target === 'active',
            'rejection_reason' => $target === 'rejected' ? ($data['reason'] ?? null) : null,
            'approved_by' => $target === 'active' ? Auth::id() : $promotion->approved_by,
            'approved_at' => $target === 'active' ? now() : $promotion->approved_at,
        ]);

        AuditLogger::record('pricing.promotion.status', $promotion, $before, ['status' => $target], $data['reason'] ?? null);

        return back()->with('success', 'Promotion status updated to '.$target.'.');
    }

    /**
     * Preview a proposed price against the margin guardrails without changing
     * anything. Used by the console's price-checker.
     */
    public function check(Request $request)
    {
        $data = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'proposed_price' => 'required|numeric|min:0',
        ])->validate();

        $product = Product::with('primaryVariant')->findOrFail($data['product_id']);
        $base = (float) ($product->primaryVariant->price ?? 0);

        $result = $this->guardrails->evaluate($product->vendor, $base, (float) $data['proposed_price']);

        return response()->json([
            'base_price' => $base,
            'guardrail' => $result,
            'recommendations' => $this->recommendations->forProduct($product),
        ]);
    }
}
