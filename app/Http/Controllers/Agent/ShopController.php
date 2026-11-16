<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentSavedProduct;
use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Region;
use App\Models\SalesRep;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class ShopController extends Controller
{
    protected function agent(): SalesRep
    {
        return request()->attributes->get('agent');
    }

    public function index(Request $request)
    {
        $agent = $this->agent();
        $query = Product::visible()->with(['translation', 'variants', 'vendor', 'category', 'images']);

        if ($search = trim((string) $request->query('q'))) {
            $query->whereHas('translation', function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            });
        }

        if ($categoryId = $request->query('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($vendorId = $request->query('vendor_id')) {
            $query->where('vendor_id', $vendorId);
        }

        if ($request->query('available') === '1') {
            $query->whereHas('variants', fn ($q) => $q->where('stock', '>', 0));
        }

        if ($request->query('discounted') === '1') {
            $query->whereHas('variants', fn ($q) => $q->whereNotNull('discount_price'));
        }

        if ($min = $request->query('min_price')) {
            $query->whereHas('variants', fn ($q) => $q->where('price', '>=', $min));
        }

        if ($max = $request->query('max_price')) {
            $query->whereHas('variants', fn ($q) => $q->where('price', '<=', $max));
        }

        $sort = $request->query('sort', 'newest');
        match ($sort) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'rating' => $query->withAvg('reviews', 'rating')->orderByDesc('reviews_avg_rating'),
            default => $query->latest(),
        };

        $products = $query->paginate(24)->withQueryString();

        $categories = Category::orderBy('id')->get();
        $vendors = \App\Models\Vendor::where('status', 'active')->where('is_approved', 1)->orderBy('shop_name')->get();
        $savedIds = AgentSavedProduct::where('sales_rep_id', $agent->id)->pluck('product_id')->all();
        $recentlyViewed = $this->recentlyViewedProducts();

        $featured = Product::visible()->with(['translation', 'variants'])->latest()->take(8)->get();
        $newArrivals = Product::visible()->with(['translation', 'variants'])->latest()->take(8)->get();
        $discounted = Product::visible()->whereHas('variants', fn ($q) => $q->whereNotNull('discount_price'))->with(['translation', 'variants'])->take(8)->get();
        $promotions = Promotion::running()->latest()->take(8)->get();
        $popularCategories = Category::withCount('products')->orderByDesc('products_count')->take(8)->get();

        $regions = Region::active()->ordered()->get();
        $locations = Location::active()->ordered()->get();

        return view('agent.shop.index', compact(
            'agent', 'products', 'categories', 'vendors', 'savedIds', 'recentlyViewed',
            'featured', 'newArrivals', 'discounted', 'promotions', 'popularCategories',
            'regions', 'locations'
        ));
    }

    public function show(Request $request, Product $product)
    {
        $agent = $this->agent();

        if (! $product->visible()->exists()) {
            abort(404);
        }

        $product->load(['translation', 'variants', 'vendor', 'category', 'images', 'reviews.customer']);

        $this->recordRecentlyViewed($product->id);

        $saved = AgentSavedProduct::where('sales_rep_id', $agent->id)->where('product_id', $product->id)->exists();
        $related = Product::visible()->where('category_id', $product->category_id)->where('id', '!=', $product->id)->with(['translation', 'variants'])->take(8)->get();
        $promotions = Promotion::running()->where(function ($q) use ($product) {
            $q->where('product_id', $product->id)->orWhere('category_id', $product->category_id);
        })->get();

        $locationId = $request->query('location_id');
        $location = $locationId ? Location::find($locationId) : null;
        $deliveryAvailable = $location ? $location->isDeliveryCovered() : null;

        return view('agent.shop.show', compact('agent', 'product', 'saved', 'related', 'promotions', 'location', 'deliveryAvailable'));
    }

    public function save(Product $product)
    {
        $agent = $this->agent();
        AgentSavedProduct::firstOrCreate([
            'sales_rep_id' => $agent->id,
            'product_id' => $product->id,
        ]);

        return back()->with('success', 'Product saved.');
    }

    public function unsave(Product $product)
    {
        $agent = $this->agent();
        AgentSavedProduct::where('sales_rep_id', $agent->id)->where('product_id', $product->id)->delete();

        return back()->with('success', 'Product removed from saved list.');
    }

    public function saved()
    {
        $agent = $this->agent();
        $products = Product::visible()->whereHas('savedByAgents', fn ($q) => $q->where('sales_rep_id', $agent->id))
            ->with(['translation', 'variants'])
            ->paginate(24);

        return view('agent.shop.saved', compact('agent', 'products'));
    }

    public function compare(Request $request)
    {
        $agent = $this->agent();
        $ids = Session::get('agent_compare', []);
        $products = Product::visible()->whereIn('id', $ids)->with(['translation', 'variants', 'category'])->get();

        return view('agent.shop.compare', compact('agent', 'products'));
    }

    public function addToCompare(Product $product)
    {
        $ids = Session::get('agent_compare', []);
        if (! in_array($product->id, $ids)) {
            $ids[] = $product->id;
            Session::put('agent_compare', array_slice($ids, -4));
        }

        return back()->with('success', 'Added to comparison.');
    }

    public function removeFromCompare(Product $product)
    {
        $ids = Session::get('agent_compare', []);
        Session::put('agent_compare', array_values(array_diff($ids, [$product->id])));

        return back()->with('success', 'Removed from comparison.');
    }

    public function addToOrder(Request $request, Product $product)
    {
        $agent = $this->agent();
        $variant = $product->variants()->where('is_primary', true)->first() ?: $product->variants()->first();
        $variantId = $request->query('variant_id', $variant?->id);
        $quantity = (int) $request->query('quantity', 1);

        return redirect()->route('agent.place-order.index', [
            'customer_id' => $request->query('customer_id'),
            'variant_id' => $variantId,
            'quantity' => $quantity,
        ]);
    }

    protected function recentlyViewedProducts()
    {
        $ids = Session::get('agent_recently_viewed', []);
        if (empty($ids)) {
            return collect();
        }

        return Product::visible()->whereIn('id', $ids)->with(['translation', 'variants'])->get()
            ->sortBy(fn ($product) => array_search($product->id, $ids));
    }

    protected function recordRecentlyViewed(int $productId): void
    {
        $ids = Session::get('agent_recently_viewed', []);
        $ids = array_values(array_diff($ids, [$productId]));
        array_unshift($ids, $productId);
        Session::put('agent_recently_viewed', array_slice($ids, 0, 12));
    }
}
