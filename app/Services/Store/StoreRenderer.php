<?php

namespace App\Services\Store;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Models\Shop;
use App\Models\StorefrontTemplate;
use App\Models\Vendor;
use App\Support\Concerns\ShapesProductRows;
use Illuminate\Support\Collection as BaseCollection;

/**
 * Resolves a vendor's store-builder layout into render-ready blocks.
 *
 * Reuses the shared page-block views but every catalogue component is scoped
 * to the owning vendor, so a store can only ever surface its own products,
 * categories and collections.
 */
class StoreRenderer
{
    use ShapesProductRows;

    public function render(Shop $shop, Vendor $vendor): array
    {
        return $this->renderRegion($shop, $vendor, 'home');
    }

    /**
     * Render one store tab. The vendor's own saved layout for the region wins;
     * otherwise the published storefront template for that region is used.
     */
    public function renderRegion(Shop $shop, Vendor $vendor, string $region = 'home'): array
    {
        $blocks = $shop->layoutFor($region);

        if (empty($blocks)) {
            $template = StorefrontTemplate::resolveForVendor($vendor->id);
            $blocks = $template ? $template->blocksFor($region) : [];
        }

        return $this->renderBlocks($blocks, $shop, $vendor);
    }

    /**
     * Render an explicit set of blocks for a vendor, used by the admin
     * storefront builder preview.
     */
    public function renderBlocks(array $blocks, Shop $shop, Vendor $vendor): array
    {
        if (empty($blocks)) {
            return [];
        }

        $clean = StoreBlocks::sanitize($blocks);
        $out = [];

        foreach ($clean as $block) {
            if (! empty($block['hidden'])) {
                continue;
            }

            $out[] = [
                'id' => $block['id'],
                'type' => $block['type'],
                'hidden' => false,
                'settings' => $block['settings'],
                'data' => $this->resolveData($block['type'], $block['settings'], $shop, $vendor),
            ];
        }

        return $out;
    }

    protected function resolveData(string $type, array $settings, Shop $shop, Vendor $vendor): array
    {
        return match ($type) {
            'product_carousel', 'product_grid' => [
                'products' => $this->products($settings, $vendor),
            ],
            'product_rows' => ['rows' => $this->productRows($settings, $vendor)],
            'category_tiles' => ['tiles' => $this->categoryTiles($settings, $shop, $vendor)],
            'category_grid' => ['categories' => $this->categoryGrid($settings, $shop, $vendor)],
            'gallery' => ['images' => $settings['images'] ?? []],
            default => [],
        };
    }

    /**
     * Expand a multi-row product section into render-ready rows, scoped to the
     * vendor. Rows without eligible products are dropped unless they declare an
     * empty-state message.
     */
    protected function productRows(array $settings, Vendor $vendor): array
    {
        return $this->buildProductRows($settings, fn (array $row) => $this->rowProducts($row, $vendor));
    }

    protected function rowProducts(array $row, Vendor $vendor): BaseCollection
    {
        $source = $row['source'] ?? 'featured';
        $limit = (int) ($row['limit'] ?? 10);

        if ($source === 'manual') {
            $ids = $this->manualProductIds($row['product_ids'] ?? null);

            if ($ids->isEmpty()) {
                return collect();
            }

            $products = Product::query()->visible()
                ->where('vendor_id', $vendor->id)
                ->whereIn('id', $ids->all())
                ->with(['translation', 'thumbnail', 'primaryVariant'])
                ->withCount('reviews')
                ->get()
                ->keyBy('id');

            return $ids->map(fn ($id) => $products->get($id))->filter()->take($limit)->values();
        }

        if (in_array($source, ['category', 'collection'], true)) {
            return $this->products([
                'source' => $source,
                'category_id' => $row['category_id'] ?? null,
                'collection_id' => $row['collection_id'] ?? null,
                'limit' => $limit,
            ], $vendor);
        }

        $mapped = match ($source) {
            'daily_deals', 'flash_sale' => 'discounted',
            'trending', 'bestsellers', 'popular' => 'hidden_gems',
            'sponsored', 'featured' => 'featured',
            default => 'new_arrivals',
        };

        return $this->products(['source' => $mapped, 'limit' => $limit], $vendor);
    }

    protected function products(array $settings, Vendor $vendor): BaseCollection
    {
        $source = $settings['source'] ?? 'featured';
        $limit = (int) ($settings['limit'] ?? 8);

        if ($source === 'collection') {
            return $this->collectionProducts($settings['collection_id'] ?? null, $vendor, $limit);
        }

        $query = Product::query()->visible()
            ->where('vendor_id', $vendor->id)
            ->with(['translation', 'thumbnail', 'primaryVariant'])
            ->withCount('reviews');

        switch ($source) {
            case 'discounted':
                $query->whereHas('variants', fn ($q) => $q->whereNotNull('discount_price')->whereColumn('discount_price', '<', 'price'))
                    ->latest('id');
                break;
            case 'hidden_gems':
                $query->orderByDesc('reviews_avg_rating')->orderByDesc('reviews_count');
                break;
            case 'category':
                $categoryId = $settings['category_id'] ?? null;
                if ($categoryId) {
                    $ids = Category::where('id', $categoryId)->orWhere('parent_category_id', $categoryId)->pluck('id');
                    $query->whereIn('category_id', $ids);
                }
                $query->latest('id');
                break;
            case 'featured':
            case 'sponsored':
                $query->where('is_sponsored', 1)->latest('id');
                break;
            case 'new_arrivals':
            case 'newest':
            default:
                $query->latest('id');
                break;
        }

        return $query->limit($limit)->get();
    }

    protected function collectionProducts($collectionId, Vendor $vendor, int $limit): BaseCollection
    {
        $collection = is_numeric($collectionId)
            ? Collection::find($collectionId)
            : Collection::where('slug', $collectionId)->first();

        if (! $collection || (int) $collection->vendor_id !== (int) $vendor->id) {
            return collect();
        }

        $query = Product::query()->visible()
            ->where('vendor_id', $vendor->id)
            ->with(['translation', 'thumbnail', 'primaryVariant'])
            ->withCount('reviews');

        $collection->applyTo($query);

        return $query->limit($limit)->get();
    }

    protected function categoryTiles(array $settings, Shop $shop, Vendor $vendor): BaseCollection
    {
        if (($settings['source'] ?? 'manual') === 'top') {
            return $this->vendorCategories($vendor)
                ->take(12)
                ->map(fn ($c) => [
                    'title' => optional($c->translation)->name ?? $c->slug,
                    'image' => optional($c->translation)->image_url ?? null,
                    'url' => route('store.products', [$shop->slug, 'category' => $c->slug]),
                ]);
        }

        return collect($settings['items'] ?? [])->map(fn ($item) => [
            'title' => $item['title'] ?? '',
            'image' => $item['image'] ?? null,
            'url' => $this->linkUrl($item['link_type'] ?? 'category', $item['link_value'] ?? null, $shop),
        ])->filter(fn ($t) => $t['title'] || $t['image'])->values();
    }

    protected function categoryGrid(array $settings, Shop $shop, Vendor $vendor): BaseCollection
    {
        $categories = $this->vendorCategories($vendor);

        if (($settings['source'] ?? 'top') === 'manual') {
            $slugs = collect($settings['items'] ?? [])->pluck('link_value')->filter()->all();
            $categories = $categories->whereIn('slug', $slugs);
        } else {
            $categories = $categories->take((int) ($settings['limit'] ?? 8));
        }

        return $categories->map(fn ($c) => [
            'title' => optional($c->translation)->name ?? $c->slug,
            'image' => optional($c->translation)->image_url ?? null,
            'url' => route('store.products', [$shop->slug, 'category' => $c->slug]),
            'count' => (bool) ($settings['show_count'] ?? true) ? (int) ($c->products_count ?? 0) : null,
        ])->values();
    }

    /**
     * Categories that actually contain this vendor's visible products.
     */
    protected function vendorCategories(Vendor $vendor): BaseCollection
    {
        return Category::with('translation')
            ->withCount(['products' => fn ($q) => $q->where('vendor_id', $vendor->id)])
            ->whereHas('products', fn ($q) => $q->where('vendor_id', $vendor->id))
            ->orderBy('id')
            ->get();
    }

    protected function linkUrl(string $type, ?string $value, Shop $shop): string
    {
        if (! $value) {
            return route('store.show', $shop->slug);
        }

        return match ($type) {
            'category' => route('store.products', [$shop->slug, 'category' => $value]),
            'collection' => route('store.collections', $shop->slug),
            default => \Illuminate\Support\Str::startsWith($value, ['http://', 'https://']) ? $value : url($value),
        };
    }
}
