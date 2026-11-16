<?php

namespace App\Services\Search;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductSearchDocument;
use App\Services\Ads\AdDeliveryService;
use App\Services\Intelligence\AlgorithmRegistry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Algorithm 02 — Search & Ranking Engine.
 *
 * Pipeline: normalize/validate → resolve context → hard eligibility scope →
 * indexed candidate retrieval → weighted relevance ranking → filters/sort →
 * sponsored placements (Juvo Ads, kept separate) → diversity → paginate.
 *
 * Degrades gracefully: if the derived index yields nothing it falls back to a
 * bounded LIKE retrieval over the index; if ranking throws, eligible results
 * are still returned. Declared as algorithm key `search_ranking`.
 */
class SearchService
{
    public const ALGORITHM_KEY = 'search_ranking';

    public const SURFACE_RESULTS = 'search_results';

    public const SURFACE_AUTOCOMPLETE = 'search_autocomplete';

    public const SORTS = ['relevance', 'price_asc', 'price_desc', 'newest', 'rating', 'promotion'];

    public function __construct(
        protected AlgorithmRegistry $registry,
        protected SearchQueryParser $parser,
        protected SearchEligibility $eligibility,
        protected SearchRankingService $ranker,
    ) {}

    public function isEnabled(): bool
    {
        return $this->registry->isActive(self::ALGORITHM_KEY);
    }

    public function config(): array
    {
        $defaults = $this->registry->definitions()[self::ALGORITHM_KEY]['config_schema']['defaults'] ?? [];

        return array_merge($defaults, $this->registry->configFor(self::ALGORITHM_KEY));
    }

    public function algorithmVersion(): ?int
    {
        return $this->registry->find(self::ALGORITHM_KEY)?->version;
    }

    /**
     * Full search for the results page.
     *
     * @return array{query:string, parsed:array, products:LengthAwarePaginator, sponsored:Collection, facets:array, selected:array, meta:array}
     */
    public function search(string $rawQuery, array $options = []): array
    {
        $started = microtime(true);
        $config = $this->config();
        $locale = $options['locale'] ?? 'en';
        $parsed = $this->parser->parse($rawQuery, $locale);

        $filters = $this->normaliseFilters($options);
        $perPage = max(1, min((int) ($options['per_page'] ?? $config['per_page'] ?? 12), (int) ($config['max_per_page'] ?? 48)));
        $page = max(1, (int) ($options['page'] ?? 1));
        $sort = in_array($options['sort'] ?? '', self::SORTS, true) ? $options['sort'] : 'relevance';

        $matched = collect();
        $indexUsed = false;

        if ($parsed['normalized'] !== '') {
            try {
                $ids = $this->retrieveCandidates($parsed, $filters, $config, $locale);
                $indexUsed = $ids->isNotEmpty();
                $matched = $this->loadEligible($ids, $filters, $config);
            } catch (\Throwable $e) {
                report($e);
                $matched = collect();
            }

            if ($matched->isNotEmpty()) {
                $matched = $this->ranker->rank($matched, $parsed, $config, $locale);
            }
        }

        $matched = $this->applyDiversity($matched, (int) ($config['diversity_max_per_vendor'] ?? 4));
        $matched = $this->applySort($matched, $sort);

        $sponsored = $this->sponsoredPlacements($options, $config, $matched);
        $sponsoredIds = $sponsored->pluck('product_id')->filter()->all();
        $organic = $matched->reject(fn (Product $p) => in_array($p->id, $sponsoredIds, true))->values();

        $total = $matched->count();
        $items = $organic->slice(($page - 1) * $perPage, $perPage)->values();
        $products = $this->paginate($items, $total, $perPage, $page);

        $facets = $this->facets($matched, $filters);
        $latency = (int) round((microtime(true) - $started) * 1000);

        $this->logQuery($parsed, $locale, $filters, $sort, $total, $latency, $options);

        return [
            'query' => $rawQuery,
            'parsed' => $parsed,
            'products' => $products,
            'sponsored' => $sponsored,
            'facets' => $facets,
            'selected' => $filters,
            'meta' => [
                'total' => $total,
                'sort' => $sort,
                'intent' => $parsed['intent'],
                'latency_ms' => $latency,
                'index_used' => $indexUsed,
                'algorithm_version' => $this->algorithmVersion(),
                'did_you_mean' => $this->didYouMean($parsed, $locale, $total),
            ],
        ];
    }

    /**
     * Autocomplete suggestions: products, categories, brands, popular queries
     * and a predictive spelling correction. Eligibility is applied so restricted
     * products never surface here.
     *
     * @return array{products:array, categories:array, brands:array, queries:array, did_you_mean:?string}
     */
    public function autocomplete(string $rawQuery, array $options = []): array
    {
        $config = $this->config();
        $locale = $options['locale'] ?? 'en';
        $parsed = $this->parser->parse($rawQuery, $locale);
        $limit = max(1, min((int) ($options['limit'] ?? $config['autocomplete_limit'] ?? 8), 20));

        if ($parsed['normalized'] === '' || mb_strlen($parsed['normalized']) < 2) {
            return [
                'products' => [],
                'categories' => [],
                'brands' => [],
                'queries' => [],
                'did_you_mean' => null,
            ];
        }

        $term = $parsed['normalized'];

        $products = [];
        try {
            $ids = $this->retrieveCandidates($parsed, [], $config, $locale)->take($limit * 4);
            $records = $this->loadEligible($ids, [], $config)->take($limit);
            $products = $records->map(fn (Product $p) => [
                'id' => $p->id,
                'slug' => $p->slug,
                'name' => $p->translations->firstWhere('language_code', $locale)?->name ?? $p->translations->first()?->name,
                'thumbnail' => $p->thumbnail ? $this->thumbnailUrl($p) : null,
                'match' => $this->matchPosition($term, (string) ($p->translations->firstWhere('language_code', $locale)?->name ?? $p->translations->first()?->name)),
            ])->values()->all();
        } catch (\Throwable $e) {
            report($e);
        }

        $categories = $this->suggestCategories($term, $locale, $limit);
        $brands = $this->suggestBrands($term, $locale, $limit);

        return [
            'products' => $products,
            'categories' => $categories,
            'brands' => $brands,
            'queries' => $this->popularQueries($term, $locale, $limit),
            'did_you_mean' => $this->didYouMeanAutocomplete($term, $locale),
        ];
    }

    /**
     * Trending / popular searches for the empty-focus state.
     *
     * @return array<int, string>
     */
    public function trending(string $locale = 'en', int $limit = 6): array
    {
        $limit = max(1, min($limit, 20));

        return Cache::remember('search.trending.'.$locale.'.'.$limit, 300, function () use ($locale, $limit) {
            try {
                return DB::table('search_queries')
                    ->where('locale', $locale)
                    ->where('surface', self::SURFACE_RESULTS)
                    ->where('is_zero_result', false)
                    ->where('normalized', '!=', '')
                    ->selectRaw('normalized, COUNT(*) as c')
                    ->groupBy('normalized')
                    ->orderByDesc('c')
                    ->limit($limit)
                    ->pluck('normalized')
                    ->all();
            } catch (\Throwable $e) {
                return [];
            }
        });
    }

    /**
     * Categories matching the term, ranked prefix-first and with product counts.
     *
     * @return array<int, array{id:int, name:string, slug:string, count:int, match:int}>
     */
    protected function suggestCategories(string $term, string $locale, int $limit): array
    {
        try {
            $rows = Category::with('translation')
                ->withCount(['products' => fn ($q) => $q->where('status', 1)])
                ->where(function ($q) use ($term) {
                    $q->whereHas('translation', fn ($t) => $t->where('name', 'like', "%{$term}%"))
                        ->orWhere('slug', 'like', "%{$term}%");
                })
                ->limit($limit * 3)
                ->get()
                ->map(function ($c) use ($term) {
                    $name = $c->translation->name ?? $c->slug;

                    return [
                        'id' => (int) $c->id,
                        'name' => $name,
                        'slug' => $c->slug,
                        'count' => (int) ($c->products_count ?? 0),
                        'match' => $this->matchPosition($term, (string) $name),
                    ];
                })
                ->all();

            return $this->rankSuggestions($rows, $limit);
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * Brands matching the term, ranked prefix-first.
     *
     * @return array<int, array{id:int, name:string, slug:string, match:int}>
     */
    protected function suggestBrands(string $term, string $locale, int $limit): array
    {
        try {
            $rows = Brand::with('translation')
                ->where(function ($q) use ($term) {
                    $q->whereHas('translation', fn ($t) => $t->where('name', 'like', "%{$term}%"))
                        ->orWhere('slug', 'like', "%{$term}%");
                })
                ->limit($limit * 3)
                ->get()
                ->map(function ($b) use ($term) {
                    $name = $b->translation->name ?? $b->slug;

                    return [
                        'id' => (int) $b->id,
                        'name' => $name,
                        'slug' => $b->slug,
                        'match' => $this->matchPosition($term, (string) $name),
                    ];
                })
                ->all();

            return $this->rankSuggestions($rows, $limit);
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * Position of the term inside the label (0 = prefix/starts with), or -1.
     * Used to sort suggestions so "prefix" matches rank above mid-string ones.
     */
    protected function matchPosition(string $term, ?string $label): int
    {
        if ($term === '' || $label === null || $label === '') {
            return -1;
        }

        $pos = mb_stripos($label, $term);

        return $pos === false ? -1 : (int) $pos;
    }

    /**
     * Sort by prefix-first match position, then name; drop non-matches; cap.
     * When $byCount is true, frequency leads and match position breaks ties.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function rankSuggestions(array $rows, int $limit, bool $byCount = false): array
    {
        $rows = array_values(array_filter($rows, fn ($r) => ($r['match'] ?? -1) >= 0));

        usort($rows, function ($a, $b) use ($byCount) {
            $posA = $a['match'] >= 0 ? $a['match'] : PHP_INT_MAX;
            $posB = $b['match'] >= 0 ? $b['match'] : PHP_INT_MAX;

            if ($byCount) {
                return ((int) ($b['count'] ?? 0) <=> (int) ($a['count'] ?? 0)) ?: ($posA <=> $posB);
            }

            return ($posA <=> $posB) ?: strcasecmp((string) $a['name'], (string) $b['name']);
        });

        return array_slice($rows, 0, $limit);
    }

    /**
     * Predictive correction for the autocomplete dropdown: the closest popular
     * query within a small edit distance of the term, only when the term itself
     * has few results. Always returns a normalised, non-empty string or null.
     */
    protected function didYouMeanAutocomplete(string $term, string $locale): ?string
    {
        if (mb_strlen($term) < 3) {
            return null;
        }

        return Cache::remember('search.dym.ac.'.$locale.'.'.md5($term), 300, function () use ($term, $locale) {
            try {
                $candidates = DB::table('search_queries')
                    ->where('locale', $locale)
                    ->where('is_zero_result', false)
                    ->where('normalized', '!=', '')
                    ->selectRaw('normalized, COUNT(*) as c')
                    ->groupBy('normalized')
                    ->orderByDesc('c')
                    ->limit(80)
                    ->pluck('normalized');
            } catch (\Throwable $e) {
                return null;
            }

            $floor = max(1, (int) floor(mb_strlen($term) / 4));
            $best = null;
            $bestDistance = PHP_INT_MAX;

            foreach ($candidates as $candidate) {
                if ($candidate === $term || str_starts_with($candidate, $term)) {
                    continue;
                }
                $distance = levenshtein($term, $candidate);
                if ($distance < $bestDistance && $distance <= $floor) {
                    $best = $candidate;
                    $bestDistance = $distance;
                }
            }

            return $best;
        });
    }

    /* ------------------------------------------------------------------ */
    /* Candidate retrieval */
    /* ------------------------------------------------------------------ */

    /**
     * @return Collection<int, int>
     */
    protected function retrieveCandidates(array $parsed, array $filters, array $config, string $locale): Collection
    {
        $limit = max(50, min((int) ($config['candidate_limit'] ?? 300), 800));
        $expr = $this->parser->toBooleanExpression($parsed);

        $hasDocument = ProductSearchDocument::query()->where('locale', $locale)->exists();

        if (! $hasDocument) {
            return $this->retrieveCandidatesFromCatalog($parsed, $locale, $limit);
        }

        $query = ProductSearchDocument::query()
            ->where('locale', $locale)
            ->when(! empty($filters['category']), fn ($q) => $q->whereIn('category_id', $filters['category']))
            ->when(! empty($filters['brand']), fn ($q) => $q->whereIn('brand_id', $filters['brand']))
            ->when(($filters['price_min'] ?? null) !== null, fn ($q) => $q->where('price_min', '>=', $filters['price_min']))
            ->when(($filters['price_max'] ?? null) !== null, fn ($q) => $q->where('price_min', '<=', $filters['price_max']));

        if ($expr !== '') {
            $query->whereRaw('MATCH(title, keywords) AGAINST (? IN BOOLEAN MODE)', [$expr])
                ->orderByRaw('MATCH(title, keywords) AGAINST (? IN BOOLEAN MODE) DESC', [$expr]);

            $ids = $query->limit($limit)->pluck('product_id');

            if ($ids->isNotEmpty()) {
                return $ids;
            }
        }

        return $this->retrieveCandidatesLike($parsed, $filters, $locale, $limit);
    }

    /**
     * Bounded LIKE fallback (short tokens below the FULLTEXT minimum, or an
     * unbuilt index). Still restricted by the derived index's indexed columns
     * and the eligibility re-check downstream.
     *
     * @return Collection<int, int>
     */
    protected function retrieveCandidatesLike(array $parsed, array $filters, string $locale, int $limit): Collection
    {
        $tokens = array_values(array_filter($parsed['tokens'], fn ($t) => mb_strlen($t) >= 2));

        if (empty($tokens)) {
            return collect();
        }

        $query = ProductSearchDocument::query()
            ->where('locale', $locale)
            ->when(! empty($filters['category']), fn ($q) => $q->whereIn('category_id', $filters['category']))
            ->when(! empty($filters['brand']), fn ($q) => $q->whereIn('brand_id', $filters['brand']));

        foreach ($tokens as $token) {
            $query->where(function ($q) use ($token) {
                $q->where('title', 'like', "%{$token}%")
                    ->orWhere('keywords', 'like', "%{$token}%");
            });
        }

        return $query->orderByDesc('popularity')->limit($limit)->pluck('product_id');
    }

    /**
     * Last-resort retrieval straight from the authoritative catalog when the
     * derived index has not been built yet. Eligibility, filters and ranking
     * are still applied downstream, so no ineligible record can leak.
     *
     * @return Collection<int, int>
     */
    protected function retrieveCandidatesFromCatalog(array $parsed, string $locale, int $limit): Collection
    {
        $tokens = array_values(array_filter($parsed['tokens'], fn ($t) => mb_strlen($t) >= 2));

        if (empty($tokens)) {
            return collect();
        }

        return DB::table('products')
            ->join('product_translations as pt', 'pt.product_id', '=', 'products.id')
            ->where('pt.language_code', $locale)
            ->where(function ($q) use ($tokens) {
                foreach ($tokens as $token) {
                    $q->where(function ($inner) use ($token) {
                        $inner->where('pt.name', 'like', "%{$token}%")
                            ->orWhere('pt.description', 'like', "%{$token}%");
                    });
                }
            })
            ->orderByDesc('products.id')
            ->limit($limit)
            ->pluck('products.id');
    }

    /**
     * @param  Collection<int, int>  $ids
     * @return Collection<int, Product>
     */
    protected function loadEligible(Collection $ids, array $filters, array $config): Collection
    {
        $ids = $ids->unique()->take(400)->all();

        if (empty($ids)) {
            return collect();
        }

        $query = Product::query()
            ->with(['translations', 'variants', 'primaryVariant', 'thumbnail', 'brand.translations', 'category.translations'])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->whereIn('id', $ids);

        $this->eligibility->scope($query, $config);

        if (! empty($filters['category'])) {
            $query->whereIn('category_id', $filters['category']);
        }
        if (! empty($filters['brand'])) {
            $query->whereIn('brand_id', $filters['brand']);
        }
        if (($filters['price_min'] ?? null) !== null || ($filters['price_max'] ?? null) !== null) {
            $query->whereHas('variants', function ($vq) use ($filters) {
                if (($filters['price_min'] ?? null) !== null) {
                    $vq->where('price', '>=', $filters['price_min']);
                }
                if (($filters['price_max'] ?? null) !== null) {
                    $vq->where('price', '<=', $filters['price_max']);
                }
            });
        }

        return $query->get();
    }

    /* ------------------------------------------------------------------ */
    /* Filters / facets / sort */
    /* ------------------------------------------------------------------ */

    protected function normaliseFilters(array $options): array
    {
        $category = array_values(array_filter(array_map('intval', (array) ($options['category'] ?? []))));
        $brand = array_values(array_filter(array_map('intval', (array) ($options['brand'] ?? []))));

        $priceMin = $options['price_min'] ?? null;
        $priceMax = $options['price_max'] ?? null;
        $priceMin = is_numeric($priceMin) ? max(0, (float) $priceMin) : null;
        $priceMax = is_numeric($priceMax) ? max(0, (float) $priceMax) : null;

        if ($priceMin !== null && $priceMax !== null && $priceMin > $priceMax) {
            [$priceMin, $priceMax] = [$priceMax, $priceMin];
        }

        return [
            'category' => $category,
            'brand' => $brand,
            'price_min' => $priceMin,
            'price_max' => $priceMax,
        ];
    }

    /**
     * Facet buckets computed from the fully-filtered eligible set, so counts
     * always match the products the shopper is actually being shown.
     */
    protected function facets(Collection $matched, array $filters): array
    {
        $categories = $matched->filter(fn (Product $p) => $p->category_id)->groupBy('category_id')
            ->map(function (Collection $group, $id) {
                $first = $group->first();

                return [
                    'id' => (int) $id,
                    'name' => $first->category?->translations->first()?->name ?? $first->category?->slug,
                    'slug' => $first->category?->slug,
                    'count' => $group->count(),
                ];
            })
            ->filter(fn ($c) => ! empty($c['name']))
            ->sortByDesc('count')
            ->values()
            ->all();

        $brands = $matched->filter(fn (Product $p) => $p->brand_id)->groupBy('brand_id')
            ->map(function ($group, $id) {
                $first = $group->first();

                return [
                    'id' => (int) $id,
                    'name' => $first->brand?->translations->first()?->name ?? $first->brand?->slug,
                    'slug' => $first->brand?->slug,
                    'count' => $group->count(),
                ];
            })
            ->filter(fn ($b) => $b['name'] !== null)
            ->sortByDesc('count')
            ->values()
            ->all();

        $bounds = $this->catalogPriceBounds();

        return [
            'categories' => $categories,
            'brands' => $brands,
            'price_min' => $bounds['min'],
            'price_max' => $bounds['max'],
        ];
    }

    protected function catalogPriceBounds(): array
    {
        return Cache::remember('search.price.bounds', 600, function () {
            $row = DB::table('product_variants')
                ->join('products', 'products.id', '=', 'product_variants.product_id')
                ->where('products.status', 1)
                ->where('products.approval_status', 'approved')
                ->where('products.visibility', 'public')
                ->selectRaw('MIN(product_variants.price) as min_price, MAX(product_variants.price) as max_price')
                ->first();

            return [
                'min' => (float) ($row->min_price ?? 0),
                'max' => (float) ($row->max_price ?? 1000),
            ];
        });
    }

    protected function applySort(Collection $matched, string $sort): Collection
    {
        return match ($sort) {
            'price_asc' => $matched->sortBy(fn (Product $p) => $this->minPriceOf($p))->values(),
            'price_desc' => $matched->sortByDesc(fn (Product $p) => $this->minPriceOf($p))->values(),
            'newest' => $matched->sortByDesc(fn (Product $p) => ($p->published_at ?? $p->created_at)?->timestamp ?? 0)->values(),
            'rating' => $matched->sortByDesc(fn (Product $p) => (float) ($p->reviews_avg_rating ?? 0))->values(),
            'promotion' => $matched->sortByDesc(fn (Product $p) => $p->search_signals['promotion'] ?? 0)->values(),
            default => $matched,
        };
    }

    protected function minPriceOf(Product $product): float
    {
        $variant = $product->variants->sortBy(fn ($v) => (float) ($v->discount_price ?: $v->price))->first();

        return (float) ($variant ? ($variant->discount_price ?: $variant->price) : 0);
    }

    /**
     * Round-robin diversification so one vendor cannot dominate the page.
     * Relevance stays primary; no forced equal share.
     */
    protected function applyDiversity(Collection $matched, int $maxPerVendor): Collection
    {
        if ($matched->count() <= $maxPerVendor || $maxPerVendor < 1) {
            return $matched->values();
        }

        $perVendor = [];
        $kept = collect();
        $overflow = collect();

        foreach ($matched as $product) {
            $vendor = $product->vendor_id ?? 0;
            if (($perVendor[$vendor] ?? 0) < $maxPerVendor) {
                $perVendor[$vendor] = ($perVendor[$vendor] ?? 0) + 1;
                $kept->push($product);
            } else {
                $overflow->push($product);
            }
        }

        return $kept->concat($overflow)->values();
    }

    /* ------------------------------------------------------------------ */
    /* Sponsored placements (Juvo Ads) — separate from organic ranking */
    /* ------------------------------------------------------------------ */

    protected function sponsoredPlacements(array $options, array $config, Collection $matched): Collection
    {
        $max = (int) ($config['max_sponsored'] ?? 2);

        if ($max <= 0 || empty($options['include_sponsored'])) {
            return collect();
        }

        try {
            $ads = app(AdDeliveryService::class)->forPlacement('search_sponsored', $max, [
                'query' => $options['query'] ?? null,
                'organic_ids' => $matched->pluck('id')->all(),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return collect();
        }

        return $ads->filter(fn ($ad) => $ad->product
            && Product::visible()->whereKey($ad->product->id)->exists())
            ->values();
    }

    /* ------------------------------------------------------------------ */
    /* Pagination / logging / helpers */
    /* ------------------------------------------------------------------ */

    protected function paginate(Collection $items, int $total, int $perPage, int $page): LengthAwarePaginator
    {
        return new Paginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    protected function logQuery(array $parsed, string $locale, array $filters, string $sort, int $total, int $latency, array $options): void
    {
        try {
            DB::table('search_queries')->insert([
                'query' => Str::limit($parsed['raw'], 250, ''),
                'normalized' => Str::limit($parsed['normalized'], 250, ''),
                'locale' => $locale,
                'surface' => self::SURFACE_RESULTS,
                'result_count' => $total,
                'is_zero_result' => $total === 0,
                'latency_ms' => $latency,
                'algorithm_key' => self::ALGORITHM_KEY,
                'algorithm_version' => $this->algorithmVersion(),
                'filters' => json_encode($filters),
                'sort' => $sort,
                'session_id' => $this->sessionId($options),
                'ip_hash' => $this->ipHash($options['ip'] ?? null),
                'occurred_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function sessionId(array $options): ?string
    {
        if (! empty($options['session_id'])) {
            return Str::limit((string) $options['session_id'], 64, '');
        }

        if (app()->runningInConsole() || ! request()->hasSession()) {
            return null;
        }

        return Str::limit((string) request()->session()->getId(), 64, '');
    }

    protected function ipHash(?string $ip): ?string
    {
        if (! $ip) {
            return null;
        }

        return hash('sha256', $ip.'|'.config('app.key'));
    }

    protected function didYouMean(array $parsed, string $locale, int $total): ?string
    {
        if ($total > 0 || $parsed['normalized'] === '') {
            return null;
        }

        return Cache::remember('search.dym.'.$locale.'.'.md5($parsed['normalized']), 300, function () use ($parsed, $locale) {
            $candidates = DB::table('search_queries')
                ->where('locale', $locale)
                ->where('is_zero_result', false)
                ->where('normalized', '!=', '')
                ->selectRaw('normalized, COUNT(*) as c')
                ->groupBy('normalized')
                ->orderByDesc('c')
                ->limit(50)
                ->pluck('normalized');

            $best = null;
            $bestDistance = PHP_INT_MAX;
            $floor = max(2, (int) floor(mb_strlen($parsed['normalized']) / 3));

            foreach ($candidates as $term) {
                $distance = levenshtein($parsed['normalized'], $term);
                if ($distance < $bestDistance && $distance <= $floor) {
                    $best = $term;
                    $bestDistance = $distance;
                }
            }

            return $best;
        });
    }

    protected function popularQueries(string $term, string $locale, int $limit): array
    {
        try {
            $rows = DB::table('search_queries')
                ->where('locale', $locale)
                ->where('surface', self::SURFACE_RESULTS)
                ->where('is_zero_result', false)
                ->where('normalized', '!=', '')
                ->where(function ($q) use ($term) {
                    $q->where('normalized', 'like', "{$term}%")
                        ->orWhere('normalized', 'like', "% {$term}%");
                })
                ->selectRaw('normalized, COUNT(*) as c')
                ->groupBy('normalized')
                ->orderByDesc('c')
                ->limit($limit * 3)
                ->get();

            $rows = $rows->map(fn ($r) => [
                'term' => $r->normalized,
                'count' => (int) $r->c,
                'match' => $this->matchPosition($term, (string) $r->normalized),
            ])->all();

            $ranked = $this->rankSuggestions($rows, $limit, true);

            return array_map(fn ($r) => ['term' => $r['term'], 'count' => $r['count']], $ranked);
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function thumbnailUrl(Product $product): ?string
    {
        $image = $product->thumbnail?->image ?? $product->thumbnail?->image_url;

        return $image
            ? (Str::startsWith($image, ['http://', 'https://']) ? $image : Storage::url($image))
            : null;
    }
}
