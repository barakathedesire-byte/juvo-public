<?php

namespace App\Services\Search;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Deterministic, explainable, weighted relevance ranking for organic search.
 *
 * Every score is a weighted sum of normalised, human-understandable signals —
 * no black boxes. Ties break on product id so identical requests are ordered
 * identically. Direct text relevance is weighted to dominate weak business
 * signals, so a paid/popular product can never bury an exact match.
 */
class SearchRankingService
{
    public const WEIGHT_DEFAULTS = [
        'exact_title' => 0.20,
        'phrase_title' => 0.10,
        'token_title' => 0.12,
        'token_keywords' => 0.10,
        'identifier' => 0.15,
        'brand' => 0.06,
        'category' => 0.05,
        'availability' => 0.05,
        'promotion' => 0.04,
        'rating' => 0.05,
        'popularity' => 0.05,
        'freshness' => 0.03,
        'engagement' => 0.05,
    ];

    /**
     * @param  Collection<int, Product>  $products
     * @return Collection<int, Product>
     */
    public function rank(Collection $products, array $parsed, array $config, string $locale = 'en'): Collection
    {
        if ($products->isEmpty()) {
            return $products;
        }

        $weights = array_merge(self::WEIGHT_DEFAULTS, $config['weights'] ?? []);
        $weightSum = array_sum($weights) ?: 1.0;

        $popularity = $this->popularityMap();
        $maxPopularity = max(1, (int) ($popularity->max() ?: 1));
        $engagement = $this->engagementMap();

        $scored = $products->map(function (Product $product) use ($parsed, $weights, $weightSum, $popularity, $maxPopularity, $engagement, $locale) {
            [$signals, $reasons] = $this->signals($product, $parsed, $popularity, $maxPopularity, $engagement, $locale);

            $score = 0.0;
            foreach ($signals as $key => $value) {
                $score += ($weights[$key] ?? 0) * $value;
            }

            $product->search_score = round($score / $weightSum, 6);
            $product->search_signals = $signals;
            $product->search_reasons = $reasons;

            return $product;
        });

        return $scored
            ->sortBy([
                ['search_score', 'desc'],
                ['id', 'asc'],
            ])
            ->values();
    }

    /**
     * @return array{0:array<string,float>,1:array<int,string>}
     */
    protected function signals(Product $product, array $parsed, Collection $popularity, int $maxPopularity, Collection $engagement, string $locale): array
    {
        $title = $this->text($this->localizedName($product, $locale));
        $keywordText = $this->text($this->keywordBlob($product, $locale));
        $normalized = $parsed['normalized'];

        $reasons = [];

        $exactTitle = ($normalized !== '' && $title === $normalized) ? 1.0 : 0.0;
        if ($exactTitle > 0) {
            $reasons[] = 'exact_title';
        }

        $phraseTitle = ($normalized !== '' && str_contains($title, $normalized)) ? 1.0 : 0.0;
        if ($phraseTitle > 0 && $exactTitle === 0.0) {
            $reasons[] = 'phrase_title';
        }

        $tokenTitle = $this->coverage($parsed['tokens'], $parsed['expanded'], $title);
        if ($tokenTitle > 0) {
            $reasons[] = 'title_match';
        }

        $tokenKeywords = $this->coverage($parsed['tokens'], $parsed['expanded'], $keywordText);
        if ($tokenKeywords > 0 && $tokenTitle === 0.0) {
            $reasons[] = 'keyword_match';
        }

        $identifier = $this->identifierScore($parsed, $product, $title, $keywordText);
        if ($identifier > 0) {
            $reasons[] = 'identifier';
        }

        $brand = $this->containsQuery($parsed['normalized'], $this->brandName($product)) ? 1.0 : 0.0;
        if ($brand > 0) {
            $reasons[] = 'brand';
        }

        $category = $this->containsQuery($parsed['normalized'], $this->categoryName($product)) ? 1.0 : 0.0;
        if ($category > 0) {
            $reasons[] = 'category';
        }

        $availability = (($product->primaryVariant->stock ?? 0) > 0 || $product->variants->contains(fn ($v) => (int) $v->stock > 0)) ? 1.0 : 0.0;

        $promotion = $product->variants->contains(fn ($v) => $v->discount_price !== null && (float) $v->discount_price > 0) ? 1.0 : 0.0;
        if ($promotion > 0) {
            $reasons[] = 'promotion';
        }

        $ratingCount = (int) ($product->reviews_count ?? 0);
        $rating = $ratingCount > 0 ? min(1.0, ((float) ($product->reviews_avg_rating ?? 0)) / 5) : 0.0;
        if ($rating >= 0.8) {
            $reasons[] = 'top_rated';
        }

        $popularityScore = min(1.0, ((int) ($popularity[$product->id] ?? 0)) / $maxPopularity);
        if ($popularityScore >= 0.5) {
            $reasons[] = 'popular';
        }

        $freshness = $this->freshness($product);
        $engagementScore = min(1.0, (float) ($engagement[$product->id] ?? 0.0));
        if ($engagementScore > 0) {
            $reasons[] = 'engagement';
        }

        $signals = [
            'exact_title' => $exactTitle,
            'phrase_title' => $phraseTitle,
            'token_title' => $tokenTitle,
            'token_keywords' => $tokenKeywords,
            'identifier' => $identifier,
            'brand' => $brand,
            'category' => $category,
            'availability' => $availability,
            'promotion' => $promotion,
            'rating' => $rating,
            'popularity' => $popularityScore,
            'freshness' => $freshness,
            'engagement' => $engagementScore,
        ];

        return [$signals, array_values(array_unique($reasons))];
    }

    /**
     * Fraction of query tokens (with synonyms) found in the normalised text.
     *
     * @param  array<int, string>  $tokens
     * @param  array<string, array<int, string>>  $expanded
     */
    protected function coverage(array $tokens, array $expanded, string $haystack): float
    {
        if (empty($tokens) || $haystack === '') {
            return 0.0;
        }

        $haystack = $this->text($haystack);
        $matched = 0;

        foreach ($tokens as $token) {
            $alternatives = array_merge([$token], $expanded[$token] ?? []);
            foreach ($alternatives as $alt) {
                $alt = $this->text($alt);
                if ($alt !== '' && str_contains($haystack, $alt)) {
                    $matched++;
                    break;
                }
            }
        }

        return $matched / count($tokens);
    }

    protected function identifierScore(array $parsed, Product $product, string $title, string $keywordText): float
    {
        if (($parsed['intent'] ?? null) !== SearchQueryParser::INTENT_EXACT_IDENTIFIER) {
            return 0.0;
        }

        foreach ($parsed['tokens'] as $token) {
            if (! preg_match('/\d/', $token)) {
                continue;
            }
            if (str_contains($title, $token) || str_contains($keywordText, $token)) {
                return 1.0;
            }
            foreach ($product->variants as $variant) {
                if ($variant->SKU && str_contains($this->text($variant->SKU), $token)) {
                    return 1.0;
                }
            }
        }

        return 0.0;
    }

    protected function containsQuery(string $normalized, string $field): bool
    {
        if ($normalized === '' || $field === '') {
            return false;
        }

        return str_contains($this->text($field), $normalized);
    }

    protected function localizedName(Product $product, string $locale): string
    {
        if ($product->relationLoaded('translations')) {
            return (string) ($product->translations->firstWhere('language_code', $locale)?->name
                ?? $product->translations->first()?->name
                ?? '');
        }

        return (string) ($product->translation?->name ?? '');
    }

    protected function keywordBlob(Product $product, string $locale): string
    {
        $parts = [
            $this->localizedName($product, $locale),
            $this->brandName($product),
            $this->categoryName($product),
            $product->product_type,
            $product->slug,
        ];

        foreach ($product->variants as $variant) {
            $parts[] = $variant->SKU;
            $parts[] = $variant->barcode;
        }

        foreach ($product->attributeValues as $value) {
            $parts[] = $value->value;
            $parts[] = $value->translated_value ?? null;
        }

        return implode(' ', array_filter($parts));
    }

    protected function brandName(Product $product): string
    {
        return (string) ($product->brand?->translations->first()?->name ?? '');
    }

    protected function categoryName(Product $product): string
    {
        return (string) ($product->category?->translations->first()?->name ?? '');
    }

    protected function text(string $value): string
    {
        $value = mb_strtolower($value);
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    /**
     * Recency signal: 1.0 for freshly published products decaying to 0.0 at 90 days.
     */
    protected function freshness(Product $product): float
    {
        $published = $product->published_at ?? $product->created_at;

        if (! $published) {
            return 0.0;
        }

        $ageDays = $published->diffInDays(now());

        return max(0.0, 1.0 - ($ageDays / 90));
    }

    protected function popularityMap(): Collection
    {
        return Cache::remember('search.popularity.30d', 600, function () {
            return DB::table('order_details')
                ->join('orders', 'orders.id', '=', 'order_details.order_id')
                ->whereNotIn('orders.status', ['canceled', 'returned'])
                ->where('orders.created_at', '>=', now()->subDays(30))
                ->selectRaw('order_details.product_id, SUM(order_details.quantity) as total_sold')
                ->groupBy('order_details.product_id')
                ->pluck('total_sold', 'product_id');
        });
    }

    /**
     * Search CTR per product from validated events. Empty baseline (0) when
     * there is no data — never fabricated.
     */
    protected function engagementMap(): Collection
    {
        return Cache::remember('search.engagement.30d', 600, function () {
            $rows = DB::table('recommendation_events')
                ->where('algorithm_key', SearchService::ALGORITHM_KEY)
                ->where('is_valid', true)
                ->whereIn('type', ['impression', 'click'])
                ->where('occurred_at', '>=', now()->subDays(30))
                ->whereNotNull('product_id')
                ->selectRaw("product_id, SUM(CASE WHEN type = 'click' THEN 1 ELSE 0 END) as clicks, SUM(CASE WHEN type = 'impression' THEN 1 ELSE 0 END) as impressions")
                ->groupBy('product_id')
                ->get();

            return $rows->mapWithKeys(function ($row) {
                $impr = (int) $row->impressions;
                $ctr = $impr > 0 ? min(1.0, ((int) $row->clicks) / $impr) : 0.0;

                return [$row->product_id => $ctr];
            });
        });
    }
}
