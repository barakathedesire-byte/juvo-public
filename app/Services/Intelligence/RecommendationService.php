<?php

namespace App\Services\Intelligence;

use App\Models\Customer;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\ProductAssociation;
use App\Models\ProductReview;
use App\Models\RecommendationEvent;
use App\Services\Ads\AdDeliveryService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Algorithm 01 — Product Discovery & Recommendation Engine.
 *
 * Produces ranked, eligible, in-stock products for every storefront
 * discovery surface. Paid placements are never mixed in here: sponsored
 * inventory is served separately through {@see sponsoredCampaigns()} which
 * delegates to the Juvo Ads delivery engine.
 */
class RecommendationService
{
    public const ALGORITHM_KEY = 'product_discovery';

    public const SURFACES = [
        'home_personalized' => 'Home — personalised rail',
        'product_similar' => 'Product — you may also like',
        'product_complementary' => 'Product — frequently bought together',
        'cart_cross_sell' => 'Cart — cross-sell',
        'category_trending' => 'Category — trending',
        'search_refine' => 'Search — refinements',
        'new_arrivals' => 'Home — new arrivals',
    ];

    /** Surfaces that still render for brand-new / anonymous shoppers. */
    protected const COLD_START_FALLBACK = ['bestsellers', 'trending', 'new_arrivals'];

    public function __construct(
        protected AlgorithmRegistry $registry,
        protected RecommendationTelemetry $telemetry,
    ) {}

    public function isEnabled(): bool
    {
        return $this->registry->isActive(self::ALGORITHM_KEY);
    }

    /**
     * Organic recommendations for a surface.
     */
    public function forSurface(string $surface, array $context = [], ?int $limit = null): Collection
    {
        $config = $this->config();
        $limit = max(1, min((int) ($limit ?? $config['default_limit'] ?? 12), (int) ($config['max_limit'] ?? 24)));

        $cacheKey = $this->cacheKey($surface, $context, $limit, $config);
        $ttl = (int) ($config['cache_ttl'] ?? 300);

        $started = microtime(true);

        $result = Cache::remember($cacheKey, $ttl, function () use ($surface, $context, $limit, $config) {
            try {
                return $this->build($surface, $context, $limit, $config);
            } catch (\Throwable $e) {
                report($e);

                return $this->fallback($context, $limit, $config);
            }
        });

        $this->telemetry->recordRender($surface, $result->count(), (int) round((microtime(true) - $started) * 1000));

        return $result;
    }

    protected function build(string $surface, array $context, int $limit, array $config): Collection
    {
        $exclude = array_filter((array) ($context['exclude'] ?? []));
        $seedIds = [];

        switch ($surface) {
            case 'product_similar':
                $seedIds = $this->associationSeed((int) ($context['product_id'] ?? 0), 'similar', $config);
                if ($seedIds->isEmpty()) {
                    // No learned similarity yet: fall back to same-category
                    // inventory rather than unrelated popular products.
                    $seedIds = collect($this->seedCategoryProductIds((int) ($context['product_id'] ?? 0)));
                }
                break;
            case 'product_complementary':
            case 'cart_cross_sell':
                $seedIds = $this->associationSeed((int) ($context['product_id'] ?? 0), 'complementary', $config);
                if ($seedIds->isEmpty() && $surface === 'cart_cross_sell') {
                    $seedIds = $this->associationSeed((int) ($context['product_id'] ?? 0), 'similar', $config);
                }
                break;
            case 'home_personalized':
                $seedIds = collect($this->personalisedProductIds($context, $config));
                break;
            case 'category_trending':
                $seedIds = collect($this->categoryProductIds((int) ($context['category_id'] ?? 0)));
                break;
            case 'search_refine':
                $seedIds = collect($this->categoryProductIds((int) ($context['category_id'] ?? 0)))
                    ->merge($this->popularProductIds(50));
                break;
            case 'new_arrivals':
                $seedIds = collect($this->newProductIds(50));
                break;
        }

        // Surfaces backed by real co-purchase evidence must never be padded
        // with generic inventory — no evidence means no rail.
        $evidenceOnly = in_array($surface, ['product_complementary', 'cart_cross_sell'], true);

        $candidateIds = $seedIds->merge($evidenceOnly ? collect() : $this->fallbackProductIds())
            ->unique()
            ->reject(fn ($id) => in_array($id, $exclude, true) || $id == ($context['product_id'] ?? null))
            ->values();

        if ($candidateIds->isEmpty()) {
            return $evidenceOnly ? collect() : $this->fallback($context, $limit, $config);
        }

        $products = Product::visible()
            ->whereIn('id', $candidateIds->take(260)->all())
            ->with(['translation', 'thumbnail', 'primaryVariant'])
            ->withCount('reviews')
            ->get();

        $products = $this->onlyInStock($products);

        if ($products->isEmpty()) {
            return $evidenceOnly ? collect() : $this->fallback($context, $limit, $config);
        }

        $scored = $this->score($products, $surface, $context, $config, $seedIds);

        $ordered = $this->diversify($scored, (int) ($config['diversity_max_per_vendor'] ?? 3));

        $result = $ordered->take($limit)->values();

        return $result->isEmpty() && ! $evidenceOnly ? $this->fallback($context, $limit, $config) : $result;
    }

    /**
     * Weighted ranking over eligible candidates.
     */
    protected function score(Collection $products, string $surface, array $context, array $config, Collection $seedIds): Collection
    {
        $weights = array_merge(
            ['association' => 0.35, 'popularity' => 0.25, 'rating' => 0.15, 'freshness' => 0.15, 'affinity' => 0.10],
            (array) ($config['weights'] ?? [])
        );

        $popularity = $this->popularityMap();
        $ratings = $this->ratingsMap();
        $associations = $this->associationScoreMap($seedIds, $surface);
        $affinity = $this->affinityMap($context, $config);

        $maxPopular = max(1, (int) ($popularity->max() ?? 1));

        return $products->map(function (Product $product) use ($weights, $popularity, $ratings, $associations, $affinity, $maxPopular) {
            $pop = (int) ($popularity[$product->id] ?? 0);
            $rating = $ratings[$product->id]->avg ?? 0;

            $signals = [
                'association' => (float) ($associations[$product->id] ?? 0),
                'popularity' => $maxPopular > 0 ? $pop / $maxPopular : 0.0,
                'rating' => $rating > 0 ? min(1.0, $rating / 5) : 0.0,
                'freshness' => $product->created_at ? max(0.0, 1 - ($product->created_at->diffInDays(now()) / 180)) : 0.0,
                'affinity' => (float) ($affinity[$product->id] ?? 0),
            ];

            $product->discovery_score = round(
                collect($weights)->reduce(
                    fn ($carry, $weight, $key) => $carry + (($signals[$key] ?? 0) * (float) $weight),
                    0.0
                ),
                6
            );
            $product->recommendation_signals = $signals;

            return $product;
        })->sortBy([
            ['discovery_score' => 'desc'],
        ])->values();
    }

    /**
     * Round-robin by vendor so one seller cannot dominate a rail.
     */
    protected function diversify(Collection $scored, int $maxPerVendor): Collection
    {
        $maxPerVendor = max(1, $maxPerVendor);
        $scored = $scored->sortByDesc('discovery_score')->values();
        $perVendor = [];
        $kept = collect();
        $overflow = collect();

        foreach ($scored as $product) {
            $vendor = $product->vendor_id ?? 0;
            if (($perVendor[$vendor] ?? 0) < $maxPerVendor) {
                $perVendor[$vendor] = ($perVendor[$vendor] ?? 0) + 1;
                $kept->push($product);
            } else {
                $overflow->push($product);
            }
        }

        return $kept->concat($overflow);
    }

    /* ------------------------------------------------------------------ */
    /* Candidate sources */
    /* ------------------------------------------------------------------ */

    protected function associationSeed(int $productId, string $type, array $config): Collection
    {
        if ($productId <= 0) {
            return collect();
        }

        return ProductAssociation::where('product_id', $productId)
            ->where('type', $type)
            ->orderByDesc('score')
            ->limit(60)
            ->pluck('related_product_id');
    }

    protected function personalisedProductIds(array $context, array $config): array
    {
        if (! ($config['personalisation'] ?? true)) {
            return [];
        }

        $subjectId = $context['subject_id'] ?? null;
        if (! $subjectId) {
            return [];
        }

        $history = RecommendationEvent::valid()
            ->where('subject_type', Customer::class)
            ->where('subject_id', $subjectId)
            ->whereIn('type', ['click', 'add_to_cart', 'purchase'])
            ->where('occurred_at', '>=', now()->subDays(45))
            ->orderByDesc('occurred_at')
            ->limit(120)
            ->get(['product_id', 'category_id']);

        if ($history->isEmpty()) {
            return [];
        }

        $productIds = $history->pluck('product_id')->filter()->unique()->take(40)->all();

        $related = ProductAssociation::whereIn('product_id', $productIds)
            ->where('type', 'similar')
            ->orderByDesc('score')
            ->limit(80)
            ->pluck('related_product_id')
            ->all();

        return array_values(array_unique(array_merge($related, $productIds)));
    }

    protected function seedCategoryProductIds(int $productId): array
    {
        if ($productId <= 0) {
            return [];
        }

        $categoryId = (int) Product::whereKey($productId)->value('category_id');

        return $this->categoryProductIds($categoryId);
    }

    protected function categoryProductIds(int $categoryId): array
    {
        if ($categoryId <= 0) {
            return $this->popularProductIds(60);
        }

        $treeIds = app(\App\Services\Homepage\HomepageService::class)->categoryTreeIds($categoryId);

        return Product::visible()->whereIn('category_id', $treeIds)->pluck('id')->all();
    }

    protected function popularProductIds(int $limit): array
    {
        return $this->popularityMap()->sortDesc()->take($limit)->keys()->map(fn ($k) => (int) $k)->all();
    }

    protected function newProductIds(int $limit): array
    {
        return Product::visible()->orderByDesc('published_at')->orderByDesc('id')->limit($limit)->pluck('id')->all();
    }

    protected function fallbackProductIds(): array
    {
        return Product::visible()->orderByDesc('id')->limit(120)->pluck('id')->all();
    }

    /* ------------------------------------------------------------------ */
    /* Signal maps (cached) */
    /* ------------------------------------------------------------------ */

    protected function popularityMap(): Collection
    {
        return Cache::remember('reco.popularity.30d', 600, function () {
            return OrderDetail::query()
                ->join('orders', 'orders.id', '=', 'order_details.order_id')
                ->whereNotIn('orders.status', ['canceled', 'returned'])
                ->where('orders.created_at', '>=', now()->subDays(30))
                ->selectRaw('order_details.product_id, SUM(order_details.quantity) as total_sold')
                ->groupBy('order_details.product_id')
                ->pluck('total_sold', 'product_id');
        });
    }

    protected function ratingsMap(): Collection
    {
        return Cache::remember('reco.ratings', 600, function () {
            return ProductReview::approved()
                ->selectRaw('product_id, AVG(rating) as avg, COUNT(*) as cnt')
                ->groupBy('product_id')
                ->get()
                ->keyBy('product_id');
        });
    }

    protected function associationScoreMap(Collection $seedIds, string $surface): Collection
    {
        if ($seedIds->isEmpty()) {
            return collect();
        }

        $type = in_array($surface, ['product_complementary', 'cart_cross_sell'], true) ? 'complementary' : 'similar';

        return ProductAssociation::whereIn('product_id', $seedIds->all())
            ->where('type', $type)
            ->selectRaw('related_product_id, MAX(score) as score')
            ->groupBy('related_product_id')
            ->pluck('score', 'related_product_id');
    }

    /**
     * Category / brand affinity from the shopper's recent behaviour.
     */
    protected function affinityMap(array $context, array $config): array
    {
        $subjectId = $context['subject_id'] ?? null;
        if (! $subjectId || ! ($config['personalisation'] ?? true)) {
            return [];
        }

        return Cache::remember('reco.affinity.'.$subjectId, 300, function () use ($subjectId) {
            $events = RecommendationEvent::valid()
                ->where('subject_type', Customer::class)
                ->where('subject_id', $subjectId)
                ->whereIn('type', ['click', 'add_to_cart', 'purchase'])
                ->where('occurred_at', '>=', now()->subDays(45))
                ->get(['category_id', 'product_id']);

            if ($events->isEmpty()) {
                return [];
            }

            $categoryWeights = $events->groupBy('category_id')
                ->filter(fn ($g, $k) => $k)
                ->map->count();
            $max = max(1, (int) $categoryWeights->max());

            $productIds = $events->pluck('product_id')->filter()->unique()->take(80)->all();
            if (empty($productIds)) {
                return [];
            }

            return Product::whereIn('id', $productIds)->get(['id', 'category_id'])
                ->mapWithKeys(function (Product $p) use ($categoryWeights, $max) {
                    return [$p->id => ((int) ($categoryWeights[$p->category_id] ?? 0)) / $max];
                })->all();
        });
    }

    /* ------------------------------------------------------------------ */
    /* Sponsored boundary — never mixed into organic ranking */
    /* ------------------------------------------------------------------ */

    /**
     * Sponsored campaigns for a surface, served by the Juvo Ads engine.
     * Kept deliberately separate from organic recommendations.
     */
    public function sponsoredCampaigns(string $placementKey, int $limit = 1): Collection
    {
        try {
            return app(AdDeliveryService::class)->forPlacement($placementKey, $limit);
        } catch (\Throwable $e) {
            report($e);

            return collect();
        }
    }

    /* ------------------------------------------------------------------ */
    /* Helpers */
    /* ------------------------------------------------------------------ */

    protected function fallback(array $context, int $limit, ?array $config = null): Collection
    {
        $config = $config ?? $this->config();

        $ids = collect()
            ->merge($this->popularProductIds(40))
            ->merge($this->newProductIds(40))
            ->unique()
            ->reject(fn ($id) => in_array($id, array_filter((array) ($context['exclude'] ?? [])), true) || $id == ($context['product_id'] ?? null))
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $products = Product::visible()
            ->whereIn('id', $ids->take(120)->all())
            ->with(['translation', 'thumbnail', 'primaryVariant'])
            ->withCount('reviews')
            ->get();

        $products = $this->onlyInStock($products);

        $popularity = $this->popularityMap();
        $max = max(1, (int) ($popularity->max() ?: 1));

        return $products
            ->sortByDesc(fn (Product $p) => (int) ($popularity[$p->id] ?? 0) * 1000 + $p->id)
            ->take(max(1, $limit))
            ->values()
            ->each(function (Product $p) use ($popularity, $max) {
                $score = round((int) ($popularity[$p->id] ?? 0) / $max, 6);
                $p->discovery_score = $score;
                $p->recommendation_signals = ['popularity' => $score];
            });
    }

    /**
     * Policy/eligibility gate applied before ranking: a product is only a
     * recommendation candidate when its primary variant is purchasable.
     * Availability is read from the authoritative catalog, never inferred.
     */
    protected function onlyInStock(Collection $products): Collection
    {
        return $products
            ->filter(fn (Product $product) => ($product->primaryVariant->stock ?? 0) > 0)
            ->values();
    }

    protected function config(): array
    {
        $defaults = $this->registry->definitions()[self::ALGORITHM_KEY]['config_schema']['defaults'] ?? [];

        return array_merge($defaults, $this->registry->configFor(self::ALGORITHM_KEY));
    }

    protected function cacheKey(string $surface, array $context, int $limit, array $config): string
    {
        $discriminator = [
            'product_id' => $context['product_id'] ?? null,
            'category_id' => $context['category_id'] ?? null,
            'subject_id' => ($config['personalisation'] ?? true) ? ($context['subject_id'] ?? null) : null,
            'q' => $context['query'] ?? null,
            'exclude' => array_slice(array_filter((array) ($context['exclude'] ?? [])), 0, 20),
        ];

        return 'reco.'.$surface.'.'.md5(json_encode($discriminator)).'.'.$limit;
    }
}
