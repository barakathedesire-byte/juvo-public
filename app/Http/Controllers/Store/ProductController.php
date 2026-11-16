<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Region;
use App\Models\Shop;
use App\Models\StoreSetting;
use App\Models\Vendor;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /*public function show($slug)
    {
        $product = Product::where('slug', $slug)
        ->with(['translation', 'thumbnail', 'reviews'])
        ->withCount('reviews')
        ->withAvg('reviews', 'rating')
        ->firstOrFail();
        return view('themes.xylo.product-detail', compact('product'));
    }*/

    public function show($slug)
    {
        $product = Product::visible()->with([
            'attributeValues.attribute',
            'attributeValues.translations',
            'translations',
            'reviews',
            'primaryVariant',
            'variants.attributeValues',
            'images',
            'category.translation',
            'category.parent.translation',
            'brand.translation',
        ])->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->where('slug', $slug)
            ->firstOrFail();

        $primaryVariant = $product->variants()->where('is_primary', true)->first();
        $inStock = $primaryVariant && $primaryVariant->stock > 0;

        $variantMap = $product->variants->map(function ($variant) {
            return [
                'id' => $variant->id,
                'attributes' => $variant->attributeValues->pluck('id')->sort()->values()->toArray(),
            ];
        });

        $breadcrumbs = [];
        $category = $product->category;

        while ($category) {
            $breadcrumbs[] = $category;
            $category = $category->parent;
        }

        $breadcrumbs = array_reverse($breadcrumbs);

        $shop = $product->vendor_id ? Shop::where('vendor_id', $product->vendor_id)->first() : null;
        $vendor = $product->vendor_id ? Vendor::find($product->vendor_id) : null;

        $recommender = app(\App\Services\Intelligence\RecommendationService::class);
        $complementary = collect();

        if ($recommender->isEnabled()) {
            $similar = $recommender->forSurface('product_similar', ['product_id' => $product->id], 10);
            $complementary = $recommender->forSurface('product_complementary', ['product_id' => $product->id], 10);
        } else {
            $similar = Product::visible()->with(['translation', 'primaryVariant', 'thumbnail'])
                ->where('category_id', $product->category_id)
                ->where('id', '!=', $product->id)
                ->inRandomOrder()
                ->limit(10)
                ->get();
        }

        $regions = Region::active()->ordered()->get();
        $rememberedRegion = session('checkout_region');
        $rememberedLocation = session('checkout_location');
        $rememberedLocationId = session('checkout_location_id');

        $settings = StoreSetting::pluck('value', 'key')->all();
        $paymentOptions = $this->paymentOptions($settings);

        $productPage = \App\Models\Page::where('system_key', 'product')->first();

        $productTemplate = \App\Services\Pages\ProductTemplates::resolve($productPage?->template);
        $appearance = \App\Services\Pages\ProductPageAppearance::forPage($productPage);

        $this->recordProductView($product);

        return view(\App\Services\Pages\ProductTemplates::view($productTemplate), compact(
            'product', 'inStock', 'variantMap', 'breadcrumbs',
            'shop', 'vendor', 'similar', 'complementary', 'regions', 'paymentOptions', 'productTemplate', 'appearance',
            'rememberedRegion', 'rememberedLocation', 'rememberedLocationId'
        ));
    }

    /**
     * Best-effort record of a real product view for the "recently viewed"
     * storefront source. Never blocks rendering; failure is reported only.
     */
    protected function recordProductView(Product $product): void
    {
        try {
            $data = [
                'type' => 'view',
                'surface' => 'product_detail',
                'product_id' => $product->id,
                'session_id' => session()->getId(),
            ];

            if ($customer = auth('customer')->user()) {
                $data['subject_type'] = $customer->getMorphClass();
                $data['subject_id'] = $customer->getKey();
            }

            app(\App\Services\Intelligence\RecommendationEventService::class)->record($data);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function paymentOptions(array $settings): array
    {
        $options = [
            ['key' => 'cod', 'label' => 'Cash on Delivery', 'icon' => 'fas fa-money-bill-wave'],
        ];

        $map = [
            'stripe' => ['Stripe', 'fab fa-stripe-s'],
            'paypal' => ['PayPal', 'fab fa-paypal'],
            'flutterwave' => ['Flutterwave', 'fas fa-credit-card'],
            'mpesa' => ['M-Pesa', 'fas fa-mobile-alt'],
        ];

        foreach ($map as $slug => $meta) {
            if (($settings['integration_enabled_'.$slug] ?? '0') === '1') {
                $options[] = ['key' => $slug, 'label' => $meta[0], 'icon' => $meta[1]];
            }
        }

        if (($settings['fleetbase_enabled'] ?? '0') === '1') {
            $options[] = ['key' => 'fleetbase', 'label' => 'Fleetbase Delivery', 'icon' => 'fas fa-truck'];
        }

        return $options;
    }

    public function getVariantPrice(Request $request)
    {
        $variantId = $request->input('variant_id');
        $productId = $request->input('product_id');
        $variant = ProductVariant::with('product')
            ->where('id', $variantId)
            ->where('product_id', $productId)
            ->first();

        if ($variant) {
            $stockStatus = $variant->stock > 0 ? __('store.product_detail.in_stock') : 'OUT OF STOCK';
            $isOutOfStock = $variant->stock <= 0;
            $salePrice = $variant->product?->hasActivePromotion() ? $variant->converted_discount_price : null;

            return response()->json([
                'success' => true,
                'price' => number_format($salePrice ?? $variant->converted_price, 2),
                'stock' => $stockStatus,
                'is_out_of_stock' => $isOutOfStock,
                'currency_symbol' => activeCurrency()->symbol,
            ]);
        } else {
            return response()->json(['success' => false]);
        }
    }
}
