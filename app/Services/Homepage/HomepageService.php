<?php

namespace App\Services\Homepage;

use App\Models\Category;
use App\Models\HomepageSection;
use App\Models\HomepageSetting;
use App\Models\HomepageSlide;
use App\Models\HomepageTile;
use App\Models\OrderDetail;
use App\Models\Product;
use Illuminate\Support\Collection;

class HomepageService
{
    protected ?Collection $catalog = null;

    public function settings(): array
    {
        return [
            'autoplay' => HomepageSetting::get('autoplay', '1') === '1',
            'autoplay_speed' => (int) HomepageSetting::get('autoplay_speed', 5000),
            'show_category_strip' => HomepageSetting::get('show_category_strip', '1') === '1',
            'show_category_sidebar' => HomepageSetting::get('show_category_sidebar', '1') === '1',
            'bg_color' => (string) HomepageSetting::get('home_bg_color', ''),
            'text_color' => (string) HomepageSetting::get('home_text_color', ''),
            'accent_color' => (string) HomepageSetting::get('home_accent_color', ''),
            'card_color' => (string) HomepageSetting::get('home_card_color', ''),
            'effects' => HomepageSetting::get('home_effects', '0') === '1',
        ];
    }

    public function slides(bool $publishedOnly = true): Collection
    {
        $query = HomepageSlide::query();

        return ($publishedOnly ? $query->published() : $query)->ordered()->get();
    }

    public function tiles(bool $publishedOnly = true): Collection
    {
        $query = HomepageTile::query();

        return ($publishedOnly ? $query->published() : $query)->ordered()->get();
    }

    /**
     * Slides shaped uniformly for the public + preview views.
     */
    public function slidesForDisplay(bool $includeDrafts = false): Collection
    {
        return $this->slides(! $includeDrafts)->map(fn ($s) => (object) [
            'title' => $s->title,
            'subtitle' => $s->subtitle,
            'description' => $s->description,
            'cta_label' => $s->cta_label,
            'cta_url' => $s->cta_url,
            'image' => $s->image,
            'mobile_image' => $s->mobile_image,
            'badge' => $s->badge,
            'text_align' => $s->text_align,
            'height' => $s->height,
            'title_size' => $s->title_size,
            'bg_color' => $s->bg_color,
            'bg_color_2' => $s->bg_color_2,
            'text_color' => $s->text_color,
            'overlay' => $s->overlay,
        ]);
    }

    /**
     * Tiles shaped uniformly with resolved destination URLs.
     */
    public function tilesForDisplay(bool $includeDrafts = false): Collection
    {
        return $this->tiles(! $includeDrafts)->map(fn ($t) => (object) [
            'title' => $t->title,
            'subtitle' => $t->subtitle,
            'image' => $t->image,
            'bg_color' => $t->bg_color,
            'text_color' => $t->text_color,
            'size' => $t->size ?: 'md',
            'shape' => $t->shape ?: 'circle',
            'url' => $this->tileUrl($t),
        ]);
    }

    /**
     * Published sections with resolved content.
     */
    public function sections(bool $includeDrafts = false): Collection
    {
        if (! $includeDrafts) {
            $this->purgeExpiredSections();
        }

        $query = HomepageSection::query();

        return ($includeDrafts ? $query : $query->published())->ordered()->get()->map(function (HomepageSection $section) {
            $resolved = $this->resolveSection($section);
            $resolved['banners'] = $this->resolveBanners($section);
            $section->resolved = $resolved;

            return $section;
        });
    }

    /**
     * Keep timed sections tidy even when the scheduler is not configured.
     */
    protected function purgeExpiredSections(): void
    {
        try {
            if (\Illuminate\Support\Facades\Cache::add('juvo_home_sections_purge', 1, 300)) {
                HomepageSection::purgeExpired();
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Banners attached to a section, with resolved image URLs.
     */
    protected function resolveBanners(HomepageSection $section): array
    {
        return collect($section->settings['banners'] ?? [])
            ->map(function (array $banner) {
                $image = $banner['image'] ?? null;

                if ($image && ! \Illuminate\Support\Str::startsWith($image, ['http://', 'https://'])) {
                    $image = \Illuminate\Support\Facades\Storage::url($image);
                }

                return [
                    'title' => $banner['title'] ?? null,
                    'subtitle' => $banner['subtitle'] ?? null,
                    'url' => $banner['url'] ?? null,
                    'size' => $banner['size'] ?? 'md',
                    'edges' => $banner['edges'] ?? 'md',
                    'shape' => $banner['shape'] ?? 'rounded',
                    'effect' => $banner['effect'] ?? 'none',
                    'bg_color' => $banner['bg_color'] ?? null,
                    'text_color' => $banner['text_color'] ?? null,
                    'gradient_from' => $banner['gradient_from'] ?? null,
                    'gradient_to' => $banner['gradient_to'] ?? null,
                    'image' => $image,
                ];
            })
            ->values()
            ->all();
    }

    protected function resolveImage(?string $image): ?string
    {
        if (! $image) {
            return null;
        }

        return \Illuminate\Support\Str::startsWith($image, ['http://', 'https://'])
            ? $image
            : \Illuminate\Support\Facades\Storage::url($image);
    }

    protected function resolveSection(HomepageSection $section): array
    {
        if ($section->type === 'image') {
            return ['image' => $this->resolveImage($section->settings['image'] ?? null)];
        }

        if ($section->type === 'announcement') {
            return ['announcement' => [
                'body' => $section->settings['body'] ?? null,
                'tone' => $section->settings['tone'] ?? 'info',
                'cta_label' => $section->settings['cta_label'] ?? null,
                'cta_url' => $section->settings['cta_url'] ?? null,
            ]];
        }

        if (in_array($section->type, ['gallery', 'slides'], true)) {
            return [];
        }

        if ($section->type === 'promo_grid') {
            return ['cards' => $this->resolvePromoCards($section)];
        }

        if (in_array($section->type, ['collection_carousel', 'collection_grid'], true)) {
            return ['collections' => $this->resolveCollections($section)];
        }

        if ($section->layout === 'rows') {
            return ['rows' => $this->resolveRows($section)];
        }

        if ($section->source === 'manual') {
            return ['products' => $this->productsForIds($section->settings['product_ids'] ?? [], $section->limit)];
        }

        if ($section->source === 'collection') {
            $slug = $section->settings['collection'] ?? null;

            return ['products' => $this->productsForCollection($slug, $section->limit)];
        }

        return ['products' => $this->productsForSource($section->source, $section->category_id, $section->limit)];
    }

    /**
     * Collections chosen by the admin, in the order they were selected. Each
     * entry carries a real product count so the storefront never invents one.
     */
    protected function resolveCollections(HomepageSection $section): array
    {
        $settings = $section->settings ?? [];
        $source = $settings['collection_source'] ?? 'collections';

        if (in_array($source, ['category', 'subcategory'], true)) {
            return $this->resolveCategoryCollections($section, $source);
        }

        $slugs = collect($section->settings['collections'] ?? [])
            ->filter()
            ->unique()
            ->values();

        if ($slugs->isEmpty()) {
            return [];
        }

        $collections = \App\Models\Collection::query()
            ->marketplace()
            ->active()
            ->whereIn('slug', $slugs->all())
            ->get()
            ->keyBy('slug');

        return $slugs
            ->map(function ($slug) use ($collections, $settings) {
                $collection = $collections->get($slug);

                if (! $collection) {
                    return null;
                }

                $query = Product::visible();
                $collection->applyTo($query);

                return [
                    'name' => $settings['collection_names'][$slug] ?? $collection->name,
                    'slug' => $collection->slug,
                    'url' => route('shop.index', ['collection' => $collection->slug]),
                    'count' => $query->count(),
                    'image' => $settings['collection_images'][$slug] ?? $collection->image,
                    'bg_color' => $settings['collection_bg_color'] ?? $collection->bg_color,
                    'text_color' => $settings['collection_text_color'] ?? $collection->text_color,
                    'size' => $collection->size ?: ($settings['collection_size'] ?? 'md'),
                    'shape' => $collection->shape ?: ($settings['collection_shape'] ?? 'rounded'),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Category-driven collection tiles: children of the chosen category for a
     * "Category" source, or its grandchildren for a "Sub-category" source. Each
     * tile links to the category page and carries a real visible product count.
     */
    protected function resolveCategoryCollections(HomepageSection $section, string $source): array
    {
        $categoryId = (int) ($section->settings['collection_category_id'] ?? 0);

        if ($categoryId <= 0) {
            return [];
        }

        $parent = Category::query()->active()->find($categoryId);

        if (! $parent) {
            return [];
        }

        $childIds = Category::query()
            ->active()
            ->where('parent_category_id', $parent->id)
            ->pluck('id');

        if ($source === 'subcategory') {
            $childIds = Category::query()
                ->active()
                ->whereIn('parent_category_id', $childIds->all())
                ->pluck('id');
        }

        if ($childIds->isEmpty()) {
            return [];
        }

        $limit = (int) ($section->limit ?: 12);

        $settings = $section->settings ?? [];

        return Category::query()
            ->active()
            ->whereIn('id', $childIds->all())
            ->with('translation')
            ->orderBy('id')
            ->take($limit)
            ->get()
            ->map(fn (Category $category) => [
                'name' => $settings['collection_names'][$category->slug] ?? ($category->translation->name ?? $category->slug),
                'slug' => $category->slug,
                'url' => route('category.show', $category->slug),
                'count' => Product::visible()->where('category_id', $category->id)->count(),
                'image' => $settings['collection_images'][$category->slug] ?? optional($category->translation)->image_url,
                'bg_color' => $settings['collection_bg_color'] ?? null,
                'text_color' => $settings['collection_text_color'] ?? null,
                'size' => $settings['collection_size'] ?? 'md',
                'shape' => $settings['collection_shape'] ?? 'rounded',
            ])
            ->values()
            ->all();
    }

    /**
     * Products chosen by hand, kept in the exact order the admin picked them.
     */
    public function productsForIds(array $ids, int $limit = 10): Collection
    {
        $ids = collect($ids)->map(fn ($id) => (int) $id)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $products = Product::visible()
            ->whereIn('id', $ids->all())
            ->with(['translation', 'thumbnail', 'primaryVariant'])
            ->withCount('reviews')
            ->withAvg(['reviews' => fn ($q) => $q->where('is_approved', 1)], 'rating')
            ->get()
            ->keyBy('id');

        return $ids->map(fn ($id) => $products->get($id))->filter()->take($limit)->values();
    }

    /**
     * A section composed of several stacked product rows, each with its own
     * source, title and column counts.
     */
    protected function resolveRows(HomepageSection $section): array
    {
        $rows = $section->settings['rows'] ?? [];

        return collect($rows)->map(function (array $row) {
            $source = $row['source'] ?? 'featured';
            $limit = (int) ($row['limit'] ?? 6);

            if ($source === 'collection') {
                $products = $this->productsForCollection($row['collection'] ?? null, $limit);
            } elseif ($source === 'manual') {
                $products = $this->productsForIds($row['product_ids'] ?? [], $limit);
            } else {
                $products = $this->productsForSource(
                    $source,
                    ! empty($row['category_id']) ? (int) $row['category_id'] : null,
                    $limit
                );
            }

            return [
                'title' => $row['title'] ?? null,
                'subtitle' => $row['subtitle'] ?? null,
                'source' => $source,
                'layout' => ($row['layout'] ?? 'carousel') === 'grid' ? 'grid' : 'carousel',
                'see_all' => (bool) ($row['see_all'] ?? false),
                'see_all_url' => $this->rowSeeAllUrl($row),
                'columns' => [
                    'desktop' => (int) ($row['columns_desktop'] ?? 5),
                    'tablet' => (int) ($row['columns_tablet'] ?? 3),
                    'mobile' => (int) ($row['columns_mobile'] ?? 2),
                ],
                'empty_state' => trim((string) ($row['empty_state'] ?? '')) ?: null,
                'products' => $products,
            ];
        })->values()->all();
    }

    protected function rowSeeAllUrl(array $row): string
    {
        return match ($row['source'] ?? null) {
            'daily_deals' => route('shop.index', ['collection' => 'deals']),
            'new_arrivals' => route('shop.index', ['collection' => 'new_arrivals']),
            'featured', 'sponsored' => route('shop.index', ['collection' => 'featured']),
            'collection' => route('shop.index', ['collection' => $row['collection'] ?? '']),
            'category' => ! empty($row['category_id'])
                ? route('shop.index', ['category' => $row['category_id']])
                : route('shop.index'),
            default => route('shop.index'),
        };
    }

    /**
     * Products for a named, marketplace-owned collection (admin-managed).
     */
    public function productsForCollection(?string $slug, int $limit = 10): Collection
    {
        if (! $slug) {
            return collect();
        }

        $collection = \App\Models\Collection::query()
            ->marketplace()
            ->active()
            ->where('slug', $slug)
            ->first();

        if (! $collection) {
            return collect();
        }

        $query = Product::visible()
            ->with(['translation', 'thumbnail', 'primaryVariant'])
            ->withCount('reviews')
            ->withAvg(['reviews' => fn ($q) => $q->where('is_approved', 1)], 'rating');

        $collection->applyTo($query);

        return $query->get()->take($limit)->values();
    }

    protected function resolvePromoCards(HomepageSection $section): array
    {
        $cards = $section->settings['cards'] ?? [];

        return collect($cards)->map(function ($card) {
            return [
                'title' => $card['title'] ?? '',
                'subtitle' => $card['subtitle'] ?? null,
                'cta_label' => $card['cta_label'] ?? 'Shop now',
                'url' => $card['url'] ?? '/products',
                'size' => ($card['size'] ?? 'sm') === 'lg' ? 'lg' : 'sm',
                'badge' => $card['badge'] ?? null,
                'image' => $card['image'] ?? null,
                'theme' => $card['theme'] ?? 'light',
            ];
        })->values()->all();
    }

    public function productsForSource(?string $source, ?int $categoryId = null, int $limit = 10, ?int $vendorId = null): Collection
    {
        $all = $this->catalog();

        $collection = match ($source) {
            'daily_deals', 'deals', 'flash_sale' => $this->byDiscount($all),
            'featured' => $all->where('is_sponsored', true)->values(),
            'sponsored' => $all->where('is_sponsored', true)->values(),
            'bestsellers' => $this->topSelling(),
            'trending' => $all->sortByDesc(fn ($p) => ($p->reviews_count ?? 0) * 10 + $p->id)->values(),
            'new_arrivals' => $all->sortByDesc('id')->values(),
            'category' => $this->inCategory($all, $categoryId),
            'vendor' => $this->vendorProducts($vendorId, $limit),
            'recommended' => $this->recommendedProducts($limit),
            'recently_viewed' => $this->recentlyViewedProducts($limit),
            'manual', 'collection' => collect(),
            default => $all->sortByDesc('id')->values(),
        };

        if ($collection->isEmpty() && in_array($source, ['featured', 'bestsellers', 'trending', 'daily_deals', 'deals'])) {
            $collection = $all->sortByDesc('id')->values();
        }

        return $collection->take($limit)->values();
    }

    /**
     * Paid placements from the Juvo Ads delivery engine. Never fabricated:
     * no eligible campaign for the placement means an empty row.
     */
    public function sponsoredProducts(int $limit): Collection
    {
        try {
            $campaigns = app(\App\Services\Intelligence\RecommendationService::class)
                ->sponsoredCampaigns('home', max(1, $limit));

            $ids = collect($campaigns)
                ->map(fn ($c) => (int) (is_object($c) ? ($c->product_id ?? 0) : (($c['product_id'] ?? 0))))
                ->filter()
                ->unique()
                ->values();

            if ($ids->isNotEmpty()) {
                $products = Product::visible()
                    ->whereIn('id', $ids->all())
                    ->with(['translation', 'thumbnail', 'primaryVariant'])
                    ->withCount('reviews')
                    ->get()
                    ->keyBy('id');

                return $ids->map(fn ($id) => $products->get($id))->filter()->take($limit)->values();
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return collect();
    }

    /**
     * Personalised recommendations for the current shopper via the
     * recommendation engine. Empty when the engine is disabled or has no
     * signal — the row shows its empty state instead of generic filler.
     */
    protected function recommendedProducts(int $limit): Collection
    {
        try {
            $recommendation = app(\App\Services\Intelligence\RecommendationService::class);

            if (! $recommendation->isEnabled()) {
                return collect();
            }

            $subjectId = auth('customer')->id();
            $products = $recommendation->forSurface(
                'home_personalized',
                $subjectId ? ['subject_id' => $subjectId] : [],
                $limit
            );

            return $products->values();
        } catch (\Throwable $e) {
            report($e);

            return collect();
        }
    }

    /**
     * Products the current shopper recently viewed, from real view events
     * recorded server-side. Falls back to the session for guests.
     */
    protected function recentlyViewedProducts(int $limit): Collection
    {
        $query = \App\Models\RecommendationEvent::query()
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
            ->limit(max($limit * 3, 30))
            ->pluck('product_id')
            ->filter()
            ->unique()
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

    protected function vendorProducts(?int $vendorId, int $limit): Collection
    {
        if (! $vendorId) {
            return collect();
        }

        return $this->catalog()
            ->filter(fn ($p) => (int) $p->vendor_id === (int) $vendorId)
            ->take($limit)
            ->values();
    }

    public function catalog(): Collection
    {
        if ($this->catalog === null) {
            $this->catalog = Product::visible()
                ->with(['translation', 'thumbnail', 'primaryVariant'])
                ->withCount('reviews')
                ->get();
        }

        return $this->catalog;
    }

    public function discountPercent(Product $product): float
    {
        $v = $product->primaryVariant;
        if (! $v || ! $v->price || ! $v->discount_price || $v->discount_price >= $v->price) {
            return 0;
        }

        return round(((float) $v->price - (float) $v->discount_price) / (float) $v->price * 100, 1);
    }

    protected function byDiscount(Collection $products): Collection
    {
        return $products
            ->filter(fn ($p) => $this->discountPercent($p) > 0)
            ->sortByDesc(fn ($p) => $this->discountPercent($p))
            ->values();
    }

    protected function inCategory(Collection $products, ?int $categoryId): Collection
    {
        if (! $categoryId) {
            return collect();
        }
        $ids = $this->categoryTreeIds($categoryId);

        return $products->filter(fn ($p) => in_array($p->category_id, $ids))->values();
    }

    public function categoryTreeIds(int $categoryId): array
    {
        return cache()->remember('juvo_home_cat_tree_'.$categoryId, 600, function () use ($categoryId) {
            $ids = [$categoryId];
            $stack = [$categoryId];
            while ($stack) {
                $parent = array_pop($stack);
                $children = Category::where('parent_category_id', $parent)->pluck('id')->all();
                foreach ($children as $child) {
                    $ids[] = $child;
                    $stack[] = $child;
                }
            }

            return array_values(array_unique($ids));
        });
    }

    protected function topSelling(): Collection
    {
        $sold = OrderDetail::query()
            ->selectRaw('product_id, SUM(quantity) as total_sold')
            ->groupBy('product_id')
            ->pluck('total_sold', 'product_id');

        return $this->catalog()
            ->sortByDesc(fn ($p) => ($sold[$p->id] ?? 0) * 100 + ($p->reviews_count ?? 0))
            ->values();
    }

    /**
     * Resolve a homepage tile into a real destination URL.
     */
    public function tileUrl(HomepageTile $tile): string
    {
        $value = $tile->link_value;

        return match ($tile->link_type) {
            'category' => $value ? route('category.show', $value) : route('shop.index'),
            'collection' => route('shop.index', ['collection' => $value ?: 'new_arrivals']),
            default => $value && \Illuminate\Support\Str::startsWith($value, ['http://', 'https://'])
                ? $value
                : ($value ?: route('shop.index')),
        };
    }
}
