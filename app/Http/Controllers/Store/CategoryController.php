<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * All-categories directory: top-level categories with their subcategories.
     * Mirrors a marketplace "browse all departments" landing page.
     */
    public function index()
    {
        $categories = Category::with([
            'translation',
            'children' => function ($query) {
                $query->where('status', 1)->with('translation');
            },
        ])
            ->whereNull('parent_category_id')
            ->where('status', 1)
            ->get();

        $categories = Category::sortTreeByName($categories);

        return view('themes.xylo.all-categories', compact('categories'));
    }

    /**
     * Display category page with products.
     */
    public function show($slug, Request $request)
    {
        $category = Category::with(['translation', 'parent.translation'])
            ->where('slug', $slug)
            ->firstOrFail();

        $categoryIds = [$category->id];
        $stack = $category->id;
        $childrenIds = Category::where('parent_category_id', $category->id)->where('status', 1)->pluck('id')->all();
        while ($childrenIds) {
            $categoryIds = array_merge($categoryIds, $childrenIds);
            $grandChildren = Category::whereIn('parent_category_id', $childrenIds)->where('status', 1)->pluck('id')->all();
            $childrenIds = $grandChildren;
        }

        $query = Product::visible()->with([
            'translations',
            'primaryVariant',
            'reviews',
            'images',
        ])
            ->withCount(['reviews' => function ($q) {
                $q->where('is_approved', 1);
            }])
            ->withAvg(['reviews' => function ($q) {
                $q->where('is_approved', 1);
            }], 'rating')
            ->where('status', 1)
            ->whereIn('category_id', $categoryIds);

        if ($request->filled('min_price')) {
            $query->whereHas('primaryVariant', function ($q) use ($request) {
                $q->where('price', '>=', $request->min_price);
            });
        }

        if ($request->filled('max_price')) {
            $query->whereHas('primaryVariant', function ($q) use ($request) {
                $q->where('price', '<=', $request->max_price);
            });
        }

        if ($request->filled('sort')) {
            switch ($request->sort) {
                case 'price_asc':
                    $query->join('product_variants', 'products.id', '=', 'product_variants.product_id')
                        ->where('product_variants.is_primary', 1)
                        ->orderBy('product_variants.price', 'asc')
                        ->select('products.*');
                    break;

                case 'price_desc':
                    $query->join('product_variants', 'products.id', '=', 'product_variants.product_id')
                        ->where('product_variants.is_primary', 1)
                        ->orderBy('product_variants.price', 'desc')
                        ->select('products.*');
                    break;

                case 'newest':
                    $query->latest();
                    break;

                case 'rating':
                    $query->withAvg('reviews', 'rating')
                        ->orderByDesc('reviews_avg_rating');
                    break;

                default:
                    $query->latest();
                    break;
            }
        } else {
            $query->latest();
        }

        $products = $query->paginate(12);

        $breadcrumbs = [];
        $parent = $category;
        while ($parent) {
            $breadcrumbs[] = $parent;
            $parent = $parent->parent;
        }
        $breadcrumbs = array_reverse($breadcrumbs);

        return view('themes.xylo.category', compact('category', 'products', 'breadcrumbs'));
    }
}
