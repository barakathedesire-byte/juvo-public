<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShopController extends Controller
{
    public function index(Request $request)
    {
        $locale = app()->getLocale();

        $filters = [
            'category' => (array) $request->input('category', []),
            'brand' => (array) $request->input('brand', []),
            'price_min' => $request->input('price_min', 0),
            'price_max' => $request->input('price_max', 1000),
            'color' => (array) $request->input('color', []),
            'size' => (array) $request->input('size', []),
        ];

        $products = Product::visible()->with(['translation', 'variants.attributeValues'])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->when(! empty($filters['category']), function ($query) use ($filters) {
                $query->whereIn('category_id', $filters['category']);
            })
            ->when(! empty($filters['brand']), function ($query) use ($filters) {
                $query->whereIn('brand_id', $filters['brand']);
            })
            ->whereHas('variants', function ($variantQuery) use ($filters) {
                $variantQuery
                    ->when($filters['price_min'], function ($q) use ($filters) {
                        $q->where('price', '>=', $filters['price_min']);
                    })
                    ->when($filters['price_max'], function ($q) use ($filters) {
                        $q->where('price', '<=', $filters['price_max']);
                    })
                    ->when(! empty($filters['color']), function ($q) use ($filters) {
                        $q->whereHas('attributeValues', function ($avQuery) use ($filters) {
                            $avQuery->whereIn('value', $filters['color'])
                                ->whereHas('attribute', function ($aQuery) {
                                    $aQuery->where('name', 'Color');
                                });
                        });
                    })
                    ->when(! empty($filters['size']), function ($q) use ($filters) {
                        $q->whereHas('attributeValues', function ($avQuery) use ($filters) {
                            $avQuery->whereIn('value', $filters['size'])
                                ->whereHas('attribute', function ($aQuery) {
                                    $aQuery->where('name', 'Size');
                                });
                        });
                    });
            });

        $this->applyCollection($products, $request->input('collection'), $request->input('sort'));

        $products = $products->paginate(12);

        $brands = Brand::with('translation')->withCount('products')->get();
        $categories = Category::with('translation')->withCount('products')->get();

        $colors = Attribute::where('name', 'Color')->first()?->values()->get() ?? collect();
        $sizes = Attribute::where('name', 'Size')->first()?->values()->get() ?? collect();
        $priceBounds = $this->priceBounds();

        if ($request->ajax()) {
            return view('themes.xylo.partials.product-list', compact('products'))->render();
        }

        return view('themes.xylo.shop', compact('products', 'categories', 'brands', 'colors', 'sizes', 'priceBounds', 'filters'));
    }

    protected function priceBounds(): array
    {
        $bounds = DB::table('product_variants')
            ->selectRaw('MIN(price) as min_price, MAX(price) as max_price')
            ->first();

        return [
            'min' => (int) floor((float) ($bounds->min_price ?? 0)),
            'max' => (int) ceil((float) ($bounds->max_price ?? 1000)) ?: 1000,
        ];
    }

    /**
     * Apply homepage "collection" presets and supported sort shortcuts so
     * promotional tiles resolve to real, non-empty product listings.
     */
    protected function applyCollection($query, ?string $collection, ?string $sort): void
    {
        $discounted = function ($q) {
            $q->whereHas('variants', function ($vq) {
                $vq->whereNotNull('discount_price')
                    ->whereColumn('discount_price', '<', 'price');
            });
        };

        switch ($collection) {
            case 'new_arrivals':
                $query->latest();
                break;
            case 'clearance':
                $discounted($query);
                $query->orderByDesc('id');
                break;
            case 'hidden_gems':
                $query->orderByDesc('reviews_avg_rating')->orderByDesc('reviews_count');
                break;
            case 'deals':
                $discounted($query);
                $query->orderByDesc('id');
                break;
            case 'featured':
                $query->where('is_sponsored', 1)->orderByDesc('id');
                break;
            default:
                $named = $collection
                    ? \App\Models\Collection::query()->marketplace()->where('slug', $collection)->where('status', 'active')->first()
                    : null;

                if ($named) {
                    $named->applyTo($query);
                } elseif ($sort === 'daily_deals' || $sort === 'deals') {
                    $discounted($query);
                    $query->orderByDesc('id');
                } elseif ($sort === 'price_asc' || $sort === 'price_desc' || $sort === 'newest') {
                    $query->latest();
                }
        }
    }
}
