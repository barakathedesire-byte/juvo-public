<?php

namespace App\Services\Store;

use App\Models\AttributeValue;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RecommendationEvent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;

class CartService
{
    /**
     * Enrich raw session cart rows into display-ready line items.
     *
     * @param  array|null  $cart  Raw cart array; defaults to the session cart.
     * @return Collection<int, array<string, mixed>>
     */
    public function lines(?array $cart = null): Collection
    {
        $cart = $cart ?? Session::get('cart', []);

        if (empty($cart)) {
            return collect();
        }

        $productIds = collect($cart)->pluck('product_id')->filter()->unique()->values()->all();
        $variantIds = collect($cart)->pluck('variant_id')->filter()->unique()->values()->all();

        $products = Product::with(['translation', 'thumbnail', 'vendor', 'shop'])
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $variants = $variantIds
            ? ProductVariant::whereIn('id', $variantIds)->get()->keyBy('id')
            : collect();

        $visible = Product::visible()->whereIn('id', $productIds)->pluck('id')->flip();

        $attributeValueIds = collect($cart)
            ->flatMap(fn ($item) => array_values($item['attributes'] ?? []))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $attributeValues = $attributeValueIds
            ? AttributeValue::with(['attribute', 'translations'])->whereIn('id', $attributeValueIds)->get()->keyBy('id')
            : collect();

        return collect($cart)->map(function ($item, $key) use ($products, $variants, $visible, $attributeValues) {
            $product = $products->get($item['product_id'] ?? null);
            $variant = $variants->get($item['variant_id'] ?? null)
                ?? ($product ? ProductVariant::where('product_id', $product->id)->where('is_primary', true)->first() : null);

            $quantity = max(1, (int) ($item['quantity'] ?? 1));

            if ($item['price'] ?? null) {
                $unitPrice = (float) $item['price'];
            } else {
                $unitPrice = (float) ($variant->discount_price ?? $variant->price ?? 0);
            }

            $stock = max(0, (int) ($variant->stock ?? 0));
            $isVisible = $product && $visible->has($product->id);

            $options = collect($item['attributes'] ?? [])
                ->map(function ($id) use ($attributeValues) {
                    $value = $attributeValues->get($id);

                    if (! $value || ! $value->attribute) {
                        return null;
                    }

                    return ['label' => $value->attribute->name, 'value' => $value->translated_value];
                })
                ->filter()
                ->values();

            return [
                'key' => (string) $key,
                'product_id' => $product->id ?? ($item['product_id'] ?? null),
                'variant_id' => $variant->id ?? ($item['variant_id'] ?? null),
                'name' => $product->translation->name ?? ($variant->name ?? 'Product'),
                'variant_label' => $item['variant_name'] ?? $variant->name ?? null,
                'options' => $options,
                'image' => $this->imageUrl($product),
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'line_total' => round($unitPrice * $quantity, 2),
                'stock' => $stock,
                'available' => $isVisible && $variant !== null && $stock >= $quantity,
                'low_stock' => $isVisible && $stock > 0 && $stock <= 5,
                'out_of_stock' => ! $isVisible || $stock <= 0,
                'product_url' => $product ? route('product.show', $product->slug) : '#',
                'store_url' => $product && $product->shop ? route('store.show', $product->shop->slug) : null,
                'vendor_id' => (int) ($product->vendor_id ?? 0),
                'vendor_name' => $product->vendor->shop_name ?? $product->vendor->name ?? 'Juvo',
            ];
        })->values();
    }

    /**
     * Products to suggest alongside the cart (same categories first, then fresh).
     */
    public function recommendations(array $excludeProductIds = [], int $limit = 8): Collection
    {
        $exclude = collect($excludeProductIds)->filter()->unique()->values()->all();

        $categoryIds = Product::whereIn('id', $exclude)->pluck('category_id')->filter()->unique()->values()->all();

        $base = Product::visible()
            ->with(['translation', 'thumbnail', 'primaryVariant'])
            ->withCount('reviews')
            ->whereNotIn('id', $exclude);

        $related = (clone $base)
            ->when($categoryIds, fn ($q) => $q->whereIn('category_id', $categoryIds))
            ->orderByDesc('is_sponsored')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        if ($related->count() >= $limit) {
            return $related->values();
        }

        $fill = Product::visible()
            ->with(['translation', 'thumbnail', 'primaryVariant'])
            ->withCount('reviews')
            ->whereNotIn('id', $related->pluck('id')->merge($exclude)->unique()->all())
            ->orderByDesc('id')
            ->limit($limit - $related->count())
            ->get();

        return $related->concat($fill)->values();
    }

    /**
     * Products the current shopper recently viewed, from real view events.
     *
     * @param  array<int>  $excludeIds
     */
    public function recentlyViewed(array $excludeIds = [], int $limit = 8): Collection
    {
        $query = RecommendationEvent::query()
            ->where('type', 'view')
            ->where('is_valid', true)
            ->whereNotNull('product_id');

        $customer = auth('customer')->user();

        if ($customer) {
            $query->where('subject_type', $customer->getMorphClass())
                ->where('subject_id', $customer->getKey());
        } else {
            $sessionId = session()->getId();

            if (! $sessionId) {
                return collect();
            }

            $query->where('session_id', $sessionId);
        }

        $ids = $query->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(max($limit * 4, 32))
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->diff($excludeIds)
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $products = Product::visible()
            ->whereIn('id', $ids->all())
            ->with(['translation', 'thumbnail', 'primaryVariant'])
            ->withCount('reviews')
            ->get()
            ->keyBy('id');

        return $ids->map(fn ($id) => $products->get($id))->filter()->take($limit)->values();
    }

    protected function imageUrl(?Product $product): ?string
    {
        $path = $product->thumbnail->image_url ?? $product->image_url ?? null;

        return $path ? Storage::url($path) : null;
    }

    public function count(): int
    {
        return (int) collect(Session::get('cart', []))->sum('quantity');
    }

    public function applyCoupon($code)
    {
        $coupon = Coupon::where('code', $code)->first();

        if (! $coupon) {
            return ['success' => false, 'message' => 'Invalid coupon code.'];
        }

        if ($reason = $coupon->rejectionReason()) {
            return ['success' => false, 'message' => $reason];
        }

        Session::put('cart_coupon', $coupon->code);

        return ['success' => true, 'message' => 'Coupon applied successfully!', 'discount' => $coupon->discount, 'type' => $coupon->type];
    }

    public function getCartTotalWithDiscount($total)
    {
        $coupon = $this->currentCoupon();

        if (! $coupon) {
            return $total;
        }

        return max(0, $total - $coupon->discountFor((float) $total));
    }

    /**
     * Resolve the coupon from the session (stored as a code string).
     */
    public function currentCoupon(): ?Coupon
    {
        $coupon = Session::get('cart_coupon');

        if ($coupon instanceof Coupon) {
            return Coupon::find($coupon->id);
        }

        if (is_string($coupon) && $coupon !== '') {
            return Coupon::where('code', $coupon)->first();
        }

        return null;
    }

    public function removeCoupon()
    {
        Session::forget('cart_coupon');
    }
}
