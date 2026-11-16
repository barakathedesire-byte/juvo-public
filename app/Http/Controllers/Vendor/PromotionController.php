<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Services\Pricing\MarginGuardrail;
use App\Services\Pricing\PriceHistoryService;
use App\Services\Pricing\PricingRecommendationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PromotionController extends Controller
{
    public function __construct(
        protected MarginGuardrail $guardrails,
        protected PriceHistoryService $history,
        protected PricingRecommendationService $recommendations,
    ) {}

    public function index()
    {
        $vendor = Auth::guard('vendor')->user();
        $products = Product::where('vendor_id', $vendor->id)
            ->with(['translation', 'primaryVariant'])
            ->orderBy('id', 'desc')
            ->get();
        $promotions = Promotion::with('product.translation')
            ->where('vendor_id', $vendor->id)
            ->orderBy('id', 'desc')
            ->get();

        $recommendations = $products
            ->mapWithKeys(fn (Product $p) => [$p->id => $this->recommendations->forProduct($p)])
            ->filter()
            ->all();

        return view('vendor.promotions.index', compact('products', 'promotions', 'recommendations'));
    }

    public function store(Request $request)
    {
        $vendor = Auth::guard('vendor')->user();

        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'title' => 'nullable|string|max:150',
            'type' => 'required|in:percent,fixed',
            'value' => 'required|numeric|min:0.01',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        $product = Product::where('id', $data['product_id'])->where('vendor_id', $vendor->id)->firstOrFail();

        $variant = ProductVariant::where('product_id', $product->id)->where('is_primary', true)->first();
        $price = $variant ? (float) $variant->price : 0.0;

        if ($data['type'] === 'percent' && (float) $data['value'] > 100) {
            return back()->withInput()->withErrors(['value' => 'A percentage discount cannot exceed 100%.']);
        }

        $proposed = $data['type'] === 'percent'
            ? round($price * (1 - (float) $data['value'] / 100), 2)
            : max(0, $price - (float) $data['value']);

        $guard = $this->guardrails->evaluate($vendor, $price, $proposed);
        if (! $guard['allowed']) {
            return back()->withInput()->withErrors([
                'value' => 'This promotion is blocked by margin policy: '.implode(' ', $guard['messages']),
            ]);
        }

        $promotion = Promotion::create([
            'vendor_id' => $vendor->id,
            'product_id' => $product->id,
            'title' => $data['title'] ?? null,
            'type' => $data['type'],
            'value' => $data['value'],
            'active' => true,
            'status' => 'active',
            'scope' => 'product',
            'funding_source' => Promotion::FUNDING_VENDOR,
            'created_by' => $vendor->id,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
        ]);

        $this->recalcVariantDiscount($product, 'promotion.create', $promotion->id);

        return redirect()->route('vendor.promotions.index')
            ->with('success', 'Promotion saved.'.($guard['level'] === 'warning' ? ' Note: '.implode(' ', $guard['messages']) : ''));
    }

    public function destroy(Promotion $promotion)
    {
        $vendor = Auth::guard('vendor')->user();
        if ((int) $promotion->vendor_id !== (int) $vendor->id) {
            abort(403);
        }

        $product = $promotion->product()->first();
        $promotion->delete();
        $this->recalcVariantDiscount($product, 'promotion.delete');

        return redirect()->route('vendor.promotions.index')->with('success', 'Promotion removed.');
    }

    protected function recalcVariantDiscount(?Product $product, string $source = 'promotion', ?int $promotionId = null)
    {
        if (! $product) {
            return;
        }

        $active = Promotion::where('product_id', $product->id)->running()
            ->orderByDesc('value')
            ->first();

        $variant = ProductVariant::where('product_id', $product->id)->where('is_primary', true)->first();
        if (! $variant) {
            return;
        }

        $old = $variant->discount_price;

        if (! $active) {
            $variant->update(['discount_price' => null]);
            $new = null;
        } else {
            $price = (float) $variant->price;
            $new = $active->type === 'percent'
                ? round($price * (1 - (float) $active->value / 100), 2)
                : max(0, $price - (float) $active->value);
            $variant->update(['discount_price' => $new]);
        }

        $this->history->recordVariantChange(
            $product,
            $variant->id,
            'discount_price',
            $old,
            $new,
            $source,
            $promotionId ? 'Promotion #'.$promotionId : null,
            ['promotion_id' => $promotionId]
        );
    }
}
