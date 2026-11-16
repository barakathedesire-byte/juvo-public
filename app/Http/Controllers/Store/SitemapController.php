<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\Shop;
use App\Models\Vendor;

class SitemapController extends Controller
{
    public function index()
    {
        $urls = [];

        $urls[] = ['loc' => route('company'), 'changefreq' => 'weekly', 'priority' => '1.0', 'lastmod' => now()];
        $urls[] = ['loc' => url('/'), 'changefreq' => 'daily', 'priority' => '0.9', 'lastmod' => now()];
        $urls[] = ['loc' => route('shop.index'), 'changefreq' => 'daily', 'priority' => '0.8', 'lastmod' => now()];
        $urls[] = ['loc' => route('support.index'), 'changefreq' => 'monthly', 'priority' => '0.4', 'lastmod' => now()];

        $categories = Category::with('translation')
            ->whereHas('products', fn ($q) => $q->visible())
            ->get(['id', 'slug', 'updated_at']);

        foreach ($categories as $category) {
            $urls[] = [
                'loc' => route('category.show', $category->slug),
                'changefreq' => 'weekly',
                'priority' => '0.7',
                'lastmod' => $category->updated_at,
            ];
        }

        $products = Product::visible()->get(['id', 'slug', 'updated_at']);

        foreach ($products as $product) {
            $urls[] = [
                'loc' => route('product.show', $product->slug),
                'changefreq' => 'weekly',
                'priority' => '0.8',
                'lastmod' => $product->updated_at,
            ];
        }

        $vendorIds = Vendor::where('status', 'active')->where('is_approved', 1)->pluck('id');

        $shops = Shop::whereIn('vendor_id', $vendorIds)->get(['id', 'slug', 'updated_at']);

        foreach ($shops as $shop) {
            $urls[] = [
                'loc' => route('store.show', $shop->slug),
                'changefreq' => 'weekly',
                'priority' => '0.6',
                'lastmod' => $shop->updated_at,
            ];
        }

        $pages = Page::where('is_system', false)
            ->where('status', 1)
            ->orderBy('updated_at', 'desc')
            ->get(['slug', 'updated_at']);

        foreach ($pages as $page) {
            $urls[] = [
                'loc' => route('page.show', $page->slug),
                'changefreq' => 'monthly',
                'priority' => '0.5',
                'lastmod' => $page->updated_at,
            ];
        }

        return response()
            ->view('themes.xylo.sitemap', compact('urls'))
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots()
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /vendor',
            'Disallow: /customer',
            'Disallow: /cart',
            'Disallow: /checkout',
            'Disallow: /search',
            'Disallow: /stripe',
            '',
            'Sitemap: '.url('/sitemap.xml'),
        ];

        return response(implode("\n", $lines)."\n")
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
