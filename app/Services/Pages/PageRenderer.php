<?php

namespace App\Services\Pages;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Page;
use App\Models\Product;
use App\Services\Homepage\HomepageService;
use App\Support\Concerns\ShapesProductRows;
use Illuminate\Support\Collection as BaseCollection;

/**
 * Resolves stored page-builder blocks into render-ready data, reusing the
 * existing catalogue/product pipeline so builder components behave exactly
 * like the homepage components.
 */
class PageRenderer
{
    use ShapesProductRows;

    public function __construct(protected HomepageService $homepage) {}

    /**
     * @return array<int, array{type: string, hidden: bool, settings: array, data: array}>
     */
    public function render(Page $page): array
    {
        $blocks = is_array($page->blocks) ? $page->blocks : [];
        $out = [];

        foreach ($blocks as $block) {
            if (! is_array($block) || empty($block['type'])) {
                continue;
            }
            if (! PageBlocks::has($block['type'])) {
                continue;
            }

            if (! empty($block['hidden'])) {
                continue;
            }

            $out[] = [
                'type' => $block['type'],
                'settings' => $block['settings'] ?? [],
                'data' => $this->resolveData($block['type'], $block['settings'] ?? []),
            ];
        }

        return $out;
    }

    /**
     * Render only the region blocks for a system page key (shop/cart/...).
     *
     * @return array<int, array{type: string, settings: array, data: array}>
     */
    public function region(string $key): array
    {
        $page = Page::where('system_key', $key)->where('status', 1)->first();

        if (! $page || ! $page->hasBuilder()) {
            return [];
        }

        return $this->render($page);
    }

    protected function resolveData(string $type, array $settings): array
    {
        return match ($type) {
            'product_carousel', 'product_grid' => [
                'products' => $this->products(
                    $settings['source'] ?? 'featured',
                    $settings['category_id'] ?? null,
                    $settings['collection_id'] ?? null,
                    (int) ($settings['limit'] ?? 8)
                ),
            ],
            'category_tiles' => ['tiles' => $this->categoryTiles($settings)],
            'category_grid' => ['categories' => $this->categoryGrid($settings)],
            'product_rows' => ['rows' => $this->productRows($settings)],
            'gallery' => ['images' => $settings['images'] ?? []],
            default => [],
        };
    }

    /**
     * Resolve each configured row in a multi-row product section into
     * render-ready data. Hidden rows are dropped; a row with no eligible
     * products is kept only when it declares an empty-state message so the
     * section can explain itself instead of fabricating inventory.
     */
    protected function productRows(array $settings): array
    {
        return $this->buildProductRows($settings, fn (array $row) => $this->productsForRow($row));
    }

    protected function productsForRow(array $row): BaseCollection
    {
        $source = $row['source'] ?? 'featured';
        $limit = (int) ($row['limit'] ?? 10);
        $sort = (string) ($row['sort'] ?? 'default');

        if ($source === 'manual') {
            return $this->applySort($this->manualProducts($row['product_ids'] ?? null, $limit), $sort);
        }

        if ($source === 'collection') {
            return $this->applySort($this->collectionProducts($row['collection_id'] ?? null, $limit), $sort);
        }

        if ($source === 'sponsored') {
            return $this->applySort($this->homepage->sponsoredProducts($limit), $sort);
        }

        $products = $this->homepage->productsForSource(
            $source,
            ! empty($row['category_id']) ? (int) $row['category_id'] : null,
            $limit,
            ! empty($row['vendor_id']) ? (int) $row['vendor_id'] : null
        );

        return $this->applySort($products, $sort);
    }

    /**
     * Explicit, admin-picked products in the order given (no fabrication:
     * unknown or hidden ids are simply dropped).
     */
    protected function manualProducts($raw, int $limit): BaseCollection
    {
        $ids = $this->manualProductIds($raw);

        if ($ids->isEmpty()) {
            return collect();
        }

        $products = Product::query()->visible()
            ->whereIn('id', $ids->all())
            ->with(['translation', 'thumbnail', 'primaryVariant'])
            ->withCount('reviews')
            ->get()
            ->keyBy('id');

        return $ids->map(fn ($id) => $products->get($id))->filter()->take($limit)->values();
    }

    protected function applySort(BaseCollection $products, ?string $sort): BaseCollection
    {
        $price = fn ($p) => (float) (optional($p->primaryVariant)->price ?? 0);

        return match ($sort) {
            'newest' => $products->sortByDesc(fn ($p) => optional($p->created_at)?->timestamp ?? 0)->values(),
            'price_asc' => $products->sortBy($price)->values(),
            'price_desc' => $products->sortByDesc($price)->values(),
            'popular' => $products->sortByDesc(fn ($p) => (int) ($p->reviews_count ?? 0) * 1000 + (int) $p->id)->values(),
            'discount' => $products->sortByDesc(fn ($p) => $this->homepage->discountPercent($p))->values(),
            default => $products->values(),
        };
    }

    protected function products(?string $source, $categoryId, $collectionId, int $limit): BaseCollection
    {
        if ($source === 'collection') {
            return $this->collectionProducts($collectionId, $limit);
        }

        return $this->homepage->productsForSource($source, $categoryId ? (int) $categoryId : null, $limit);
    }

    protected function collectionProducts($collectionId, int $limit): BaseCollection
    {
        $collection = is_numeric($collectionId)
            ? Collection::find($collectionId)
            : Collection::where('slug', $collectionId)->first();

        if (! $collection) {
            return collect();
        }

        $query = Product::query()->visible()
            ->with(['translation', 'thumbnail', 'primaryVariant'])
            ->withCount('reviews');

        $collection->applyTo($query);

        return $query->limit($limit)->get();
    }

    protected function categoryTiles(array $settings): BaseCollection
    {
        $source = $settings['source'] ?? 'manual';

        if ($source === 'top') {
            return Category::where('status', 1)
                ->whereNull('parent_category_id')
                ->with('translation')
                ->orderBy('id')
                ->take(12)
                ->get()
                ->map(fn ($c) => [
                    'title' => optional($c->translation)->name ?? $c->slug,
                    'image' => optional($c->translation)->image_url ?? null,
                    'url' => route('category.show', $c->slug),
                ]);
        }

        $items = $settings['items'] ?? [];

        return collect($items)->map(function ($item) {
            return [
                'title' => $item['title'] ?? '',
                'image' => $item['image'] ?? null,
                'url' => $this->linkUrl($item['link_type'] ?? 'category', $item['link_value'] ?? null),
            ];
        })->filter(fn ($t) => $t['title'] || $t['image'])->values();
    }

    protected function categoryGrid(array $settings): BaseCollection
    {
        $source = $settings['source'] ?? 'top';

        if ($source === 'manual') {
            $slugs = collect($settings['items'] ?? [])->pluck('link_value')->filter()->all();

            return Category::where('status', 1)->whereIn('slug', $slugs)
                ->with('translation')
                ->withCount('products')
                ->get()
                ->map(fn ($c) => $this->categoryCard($c, (bool) ($settings['show_count'] ?? true)));
        }

        return Category::where('status', 1)
            ->whereNull('parent_category_id')
            ->with('translation')
            ->withCount('products')
            ->orderBy('id')
            ->take((int) ($settings['limit'] ?? 8))
            ->get()
            ->map(fn ($c) => $this->categoryCard($c, (bool) ($settings['show_count'] ?? true)));
    }

    protected function categoryCard(Category $category, bool $withCount): array
    {
        return [
            'title' => optional($category->translation)->name ?? $category->slug,
            'image' => optional($category->translation)->image_url ?? null,
            'url' => route('category.show', $category->slug),
            'count' => $withCount ? (int) ($category->products_count ?? 0) : null,
        ];
    }

    protected function linkUrl(string $type, ?string $value): string
    {
        if (! $value) {
            return route('shop.index');
        }

        return match ($type) {
            'category' => route('category.show', $value),
            'collection' => route('shop.index', ['collection' => $value]),
            default => \Illuminate\Support\Str::startsWith($value, ['http://', 'https://']) ? $value : url($value),
        };
    }
}
