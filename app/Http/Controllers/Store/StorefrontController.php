<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Shop;
use App\Models\Vendor;
use App\Services\Store\StoreRenderer;
use Illuminate\Http\Request;

class StorefrontController extends Controller
{
    public function __construct(protected StoreRenderer $storeRenderer) {}

    public function show(string $slug)
    {
        [$shop, $vendor] = $this->resolve($slug);

        $blocks = $this->storeRenderer->render($shop, $vendor);

        $products = Product::visible()
            ->where('vendor_id', $vendor->id)
            ->with(['translation', 'variants.attributeValues', 'primaryVariant'])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->latest()
            ->paginate(12);

        $featured = Product::visible()
            ->where('vendor_id', $vendor->id)
            ->where('is_sponsored', 1)
            ->with(['translation', 'primaryVariant'])
            ->withAvg('reviews', 'rating')
            ->latest()
            ->limit(8)
            ->get();

        [$rating, $ratingCount] = $this->rating($vendor);

        $categories = Category::with('translation')
            ->withCount('products')
            ->whereHas('products', fn ($q) => $q->where('vendor_id', $vendor->id))
            ->orderBy('id')
            ->get();

        return view('themes.xylo.store', compact(
            'shop', 'vendor', 'products', 'featured', 'blocks', 'rating', 'ratingCount', 'categories'
        ));
    }

    public function products(Request $request, string $slug)
    {
        [$shop, $vendor] = $this->resolve($slug);

        $query = Product::visible()
            ->where('vendor_id', $vendor->id)
            ->with(['translation', 'variants.attributeValues', 'primaryVariant'])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating');

        if ($term = $request->string('q')->trim()->toString()) {
            $query->whereHas('translation', fn ($t) => $t->where('name', 'like', '%'.$term.'%'));
        }

        if ($category = $request->string('category')->trim()->toString()) {
            $categoryIds = Category::where('slug', $category)->pluck('id');
            $childIds = Category::whereIn('parent_category_id', $categoryIds)->pluck('id');
            $query->whereIn('category_id', $categoryIds->merge($childIds));
        }

        switch ($request->string('sort')->trim()->toString()) {
            case 'price_asc':
                $query->orderBy(
                    \App\Models\ProductVariant::select('price')
                        ->whereColumn('product_id', 'products.id')
                        ->orderBy('price')
                        ->limit(1)
                );
                break;
            case 'price_desc':
                $query->orderByDesc(
                    \App\Models\ProductVariant::select('price')
                        ->whereColumn('product_id', 'products.id')
                        ->orderByDesc('price')
                        ->limit(1)
                );
                break;
            case 'popular':
                $query->orderByDesc('reviews_count')->orderByDesc('reviews_avg_rating');
                break;
            default:
                $query->latest();
        }

        $products = $query->paginate(24)->withQueryString();

        [$rating, $ratingCount] = $this->rating($vendor);

        $categories = Category::with('translation')
            ->whereHas('products', fn ($q) => $q->where('vendor_id', $vendor->id))
            ->orderBy('id')
            ->get();

        $blocks = $this->storeRenderer->renderRegion($shop, $vendor, 'products');

        return view('themes.xylo.store-products', compact(
            'shop', 'vendor', 'products', 'rating', 'ratingCount', 'categories', 'blocks'
        ));
    }

    public function collections(string $slug)
    {
        [$shop, $vendor] = $this->resolve($slug);

        $collections = Collection::forVendor($vendor->id)
            ->active()
            ->ordered()
            ->get()
            ->map(function (Collection $collection) use ($vendor) {
                $query = Product::visible()
                    ->where('vendor_id', $vendor->id)
                    ->with(['translation', 'primaryVariant'])
                    ->withAvg('reviews', 'rating');

                $collection->applyTo($query);

                $collection->setRelation('previewProducts', $query->limit(8)->get());

                return $collection;
            })
            ->filter(fn (Collection $c) => $c->previewProducts->isNotEmpty());

        [$rating, $ratingCount] = $this->rating($vendor);

        $blocks = $this->storeRenderer->renderRegion($shop, $vendor, 'collections');

        return view('themes.xylo.store-collections', compact(
            'shop', 'vendor', 'collections', 'rating', 'ratingCount', 'blocks'
        ));
    }

    public function about(string $slug)
    {
        [$shop, $vendor] = $this->resolve($slug);
        [$rating, $ratingCount] = $this->rating($vendor);

        $blocks = $this->storeRenderer->renderRegion($shop, $vendor, 'about');

        return view('themes.xylo.store-about', compact('shop', 'vendor', 'rating', 'ratingCount', 'blocks'));
    }

    /**
     * Resolve an approved, active storefront by slug.
     */
    protected function resolve(string $slug): array
    {
        $shop = Shop::where('slug', $slug)->firstOrFail();

        $vendor = Vendor::whereKey($shop->vendor_id)
            ->where('status', 'active')
            ->where('is_approved', 1)
            ->firstOrFail();

        return [$shop, $vendor];
    }

    protected function rating(Vendor $vendor): array
    {
        $base = ProductReview::approved()
            ->whereHas('product', fn ($q) => $q->where('vendor_id', $vendor->id));

        return [(float) (clone $base)->avg('rating'), (clone $base)->count()];
    }
}
