<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Store\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class CartController extends Controller
{
    protected $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    public function addToCart(Request $request)
    {
        $productId = $request->product_id;
        $quantity = (int) ($request->quantity ?? 1);
        $attributeValueIds = $request->attribute_value_ids ?? [];

        $product = Product::visible()->with('thumbnail')->find($productId);

        if (! $product) {
            return response()->json([
                'message' => 'This product is no longer available.',
            ], 422);
        }

        $variant = null;
        if ($product->product_type == 'simple') {
            $variant = $product->variants()->where('is_primary', 1)->first();
        } else {
            $variant = $this->matchVariant($productId, $attributeValueIds);
        }

        if (! $variant) {
            return response()->json([
                'message' => 'Selected variant is not available.',
            ], 422);
        }

        $attributePairs = [];
        foreach ($attributeValueIds as $attributeValueId) {
            $attributeValue = \App\Models\AttributeValue::with('attribute')->find($attributeValueId);
            if ($attributeValue && $attributeValue->attribute) {
                $attributePairs[$attributeValue->attribute->id] = $attributeValue->id;
            }
        }

        $availableStock = (int) ($variant->stock ?? 0);

        if ($availableStock <= 0) {
            return response()->json([
                'message' => 'This item is out of stock.',
            ], 422);
        }

        $cart = Session::get('cart', []);

        $attributeValueIdsSorted = collect($attributeValueIds)->sort()->values()->implode('_');
        $key = "cart_{$productId}_{$attributeValueIdsSorted}";

        $currentQuantity = isset($cart[$key]) ? (int) $cart[$key]['quantity'] : 0;

        if ($currentQuantity + $quantity > $availableStock) {
            return response()->json([
                'message' => "Only {$availableStock} in stock.",
            ], 422);
        }

        if (isset($cart[$key])) {
            $cart[$key]['quantity'] = $currentQuantity + $quantity;
        } else {
            $cart[$key] = [
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'variant_name' => $variant->name ?? $product->translation->name,
                'price' => $variant->converted_discount_price ?? $variant->converted_price,
                'quantity' => $quantity,
                'image' => optional($variant->images->first() ?? $product->thumbnail)->image_url,
                'attributes' => $attributePairs,
            ];
        }

        Session::put('cart', $cart);
        Session::put('cart_count', $this->cartCount($cart));

        return response()->json([
            'message' => __('store.product_detail.cart_success'),
            'cart' => $cart,
            'cart_count' => Session::get('cart_count'),
        ]);
    }

    private function matchVariant($productId, array $attributeValueIds)
    {
        if (empty($attributeValueIds)) {
            return ProductVariant::where('product_id', $productId)->where('is_primary', true)->first();
        }

        $variants = ProductVariant::with('attributeValues')
            ->where('product_id', $productId)
            ->get();

        foreach ($variants as $variant) {
            $variantAttrIds = $variant->attributeValues->pluck('id')->sort()->values();
            if ($variantAttrIds->toArray() === collect($attributeValueIds)->sort()->values()->toArray()) {
                return $variant;
            }
        }

        return null;
    }

    public function updateCart(Request $request)
    {
        $data = $request->validate([
            'cart' => 'required|array',
            'cart.*.key' => 'nullable|string',
            'cart.*.product_id' => 'nullable',
            'cart.*.quantity' => 'required|integer',
        ]);

        $cart = Session::get('cart', []);

        foreach ($data['cart'] as $item) {
            $key = $this->resolveKey($cart, $item);

            if ($key === null) {
                continue;
            }

            $quantity = max(1, (int) $item['quantity']);
            $stock = $this->variantStock($cart[$key]);

            if ($stock > 0) {
                $quantity = min($quantity, $stock);
            }

            $cart[$key]['quantity'] = $quantity;
        }

        Session::put('cart', $cart);
        Session::put('cart_count', $this->cartCount($cart));

        return response()->json([
            'success' => true,
            'message' => 'Cart updated successfully!',
            'cart' => $cart,
            'cart_count' => Session::get('cart_count'),
        ]);
    }

    public function viewCart(Request $request)
    {
        if ($request->filled('region')) {
            Session::put('cart_region', $request->string('region')->toString());
        }

        if ($request->filled('region')) {
            return redirect()->route('cart.view');
        }

        $cart = Session::get('cart', []);
        $lines = $this->cartService->lines();
        $saved = $this->cartService->lines(Session::get('cart_saved', []));
        $groups = $lines->groupBy('vendor_id');

        $customer = auth('customer')->user();
        $region = Session::get('cart_region')
            ?: $customer?->addresses()->where('is_default', true)->value('state');
        $location = Session::get('checkout_location');

        $quote = app(\App\Services\Store\CheckoutPricingService::class)->quote(
            $lines->map(fn ($line) => ['unit_price' => $line['unit_price'], 'quantity' => $line['quantity']])->all(),
            $this->cartService->currentCoupon(),
            $region,
            null,
            ['location' => $location]
        );

        $exclude = $lines->pluck('product_id')->merge($saved->pluck('product_id'))->unique()->values()->all();

        return view('themes.xylo.cart', [
            'cart' => Session::get('cart', []),
            'saved' => $saved,
            'groups' => $groups,
            'quote' => $quote,
            'coupon' => $this->cartService->currentCoupon(),
            'region' => $region,
            'location' => $location,
            'locationId' => Session::get('checkout_location_id'),
            'regions' => \App\Models\Region::active()->ordered()->get(),
            'recommendations' => $this->cartService->recommendations($exclude, 8),
            'recent' => $this->cartService->recentlyViewed($exclude, 8),
            'currency' => activeCurrency(),
        ]);
    }

    public function saveForLater(Request $request)
    {
        $key = $request->input('key');
        $cart = Session::get('cart', []);

        if ($key && isset($cart[$key])) {
            $saved = Session::get('cart_saved', []);
            $saved[$key] = $cart[$key];
            unset($cart[$key]);

            Session::put('cart', $cart);
            Session::put('cart_saved', $saved);
            Session::put('cart_count', $this->cartCount($cart));
        }

        return response()->json([
            'success' => true,
            'message' => 'Saved for later.',
            'cart_count' => Session::get('cart_count'),
        ]);
    }

    public function moveToCart(Request $request)
    {
        $key = $request->input('key');
        $saved = Session::get('cart_saved', []);

        if ($key && isset($saved[$key])) {
            $cart = Session::get('cart', []);
            $item = $saved[$key];
            $stock = $this->variantStock($item);

            if ($stock > 0) {
                $item['quantity'] = min(max(1, (int) $item['quantity']), $stock);
            }

            $cart[$key] = $item;
            unset($saved[$key]);

            Session::put('cart', $cart);
            Session::put('cart_saved', $saved);
            Session::put('cart_count', $this->cartCount($cart));
        }

        return response()->json([
            'success' => true,
            'message' => 'Moved to cart.',
            'cart_count' => Session::get('cart_count'),
        ]);
    }

    public function clearCart()
    {
        Session::forget(['cart', 'cart_count', 'cart_coupon', 'cart_saved']);

        return response()->json([
            'success' => true,
            'message' => 'Cart cleared.',
            'cart_count' => 0,
        ]);
    }

    public function removeFromCart(Request $request)
    {
        $cart = Session::get('cart', []);

        $key = $this->resolveKey($cart, [
            'key' => $request->input('key'),
            'product_id' => $request->input('product_id'),
        ]);

        if ($key !== null) {
            unset($cart[$key]);
            Session::put('cart', $cart);
        }

        Session::put('cart_count', $this->cartCount($cart));

        if (empty($cart)) {
            session()->forget('cart_coupon');
        }

        return response()->json([
            'message' => __('store.cart.product_removed'),
            'cart' => $cart,
            'cart_count' => Session::get('cart_count'),
        ]);
    }

    /**
     * Resolve a cart line key from an incoming payload, preferring an explicit
     * `key` and falling back to the historical `product_id`-as-key contract.
     */
    private function resolveKey(array $cart, array $item): ?string
    {
        $candidates = [$item['key'] ?? null, $item['product_id'] ?? null];

        foreach ($candidates as $candidate) {
            if ($candidate !== null && $candidate !== '' && isset($cart[$candidate])) {
                return (string) $candidate;
            }
        }

        return null;
    }

    private function variantStock(array $cartItem): int
    {
        if (empty($cartItem['variant_id'])) {
            return 0;
        }

        return max(0, (int) optional(ProductVariant::find($cartItem['variant_id']))->stock);
    }

    private function cartCount(array $cart): int
    {
        return (int) array_sum(array_column($cart, 'quantity'));
    }

    public function applyCoupon(Request $request)
    {
        $request->validate(['code' => 'required|string']);

        $result = $this->cartService->applyCoupon($request->code);

        return response()->json($result);
    }

    public function removeCoupon()
    {
        $this->cartService->removeCoupon();

        return response()->json(['success' => true, 'message' => 'Coupon removed successfully!']);
    }
}
