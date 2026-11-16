<?php

namespace App\Services\Search;

use App\Models\Brand;
use App\Models\Category;
use App\Models\SearchSynonym;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Turns raw, untrusted input into a structured query: normalised text, tokens,
 * managed-synonym expansion and a coarse intent. Pure and side-effect free so
 * it can be unit-tested and reused by the storefront and admin preview.
 */
class SearchQueryParser
{
    public const INTENT_EXACT_IDENTIFIER = 'exact_identifier';

    public const INTENT_BRAND = 'brand';

    public const INTENT_CATEGORY = 'category';

    public const INTENT_PRICE = 'price_constrained';

    public const INTENT_BROAD = 'broad';

    /**
     * @return array{
     *     raw:string, normalized:string, tokens:array<int,string>,
     *     expanded:array<string,array<int,string>>, intent:string, has_numeric:bool
     * }
     */
    public function parse(?string $raw, string $locale = 'en'): array
    {
        $raw = (string) $raw;
        $normalized = $this->normalize($raw);
        $tokens = $this->tokens($normalized);

        $synonyms = $this->synonymMap($locale);
        $expanded = [];
        foreach ($tokens as $token) {
            $expanded[$token] = $synonyms[$token] ?? [];
        }

        return [
            'raw' => $raw,
            'normalized' => $normalized,
            'tokens' => $tokens,
            'expanded' => $expanded,
            'intent' => $this->detectIntent($normalized, $tokens, $synonyms),
            'has_numeric' => (bool) preg_match('/\d/', $normalized),
        ];
    }

    /**
     * Normalise to lowercase alphanumerics with single spaces.
     */
    public function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;

        return Str::limit(trim(preg_replace('/\s+/', ' ', $value) ?? $value), 120, '');
    }

    /**
     * @return array<int, string>
     */
    public function tokens(string $normalized): array
    {
        if ($normalized === '') {
            return [];
        }

        $tokens = array_values(array_filter(explode(' ', $normalized), fn ($t) => $t !== ''));

        return array_slice(array_values(array_unique($tokens)), 0, 8);
    }

    /**
     * Build a MySQL BOOLEAN-mode expression. Every token is required (+),
     * synonyms are alternatives, and tokens are prefix-matched so partial
     * words still retrieve candidates. Identifiers keep whole-token matching.
     */
    public function toBooleanExpression(array $parsed): string
    {
        $parts = [];

        foreach ($parsed['tokens'] as $token) {
            if (mb_strlen($token) < 2) {
                continue;
            }

            $alternatives = array_merge([$token], $parsed['expanded'][$token] ?? []);
            $alternatives = array_values(array_unique(array_filter(array_map([$this, 'safeToken'], $alternatives))));

            if (empty($alternatives)) {
                continue;
            }

            $group = array_map(fn ($term) => $term.'*', $alternatives);
            $parts[] = '+('.implode(' ', $group).')';
        }

        return implode(' ', $parts);
    }

    protected function safeToken(string $token): string
    {
        $token = preg_replace('/[^\p{L}\p{N}]/u', '', $token) ?? '';

        return mb_substr($token, 0, 40);
    }

    protected function detectIntent(string $normalized, array $tokens, array $synonyms): string
    {
        if ($normalized === '') {
            return self::INTENT_BROAD;
        }

        // A token mixing letters and digits (e.g. "iphone13", "ab1234") is
        // treated as an identifier: exact matching is preserved.
        foreach ($tokens as $token) {
            if (preg_match('/^[a-z]+\d+[a-z0-9]*$|^\d+[a-z]+\d*$/', $token)) {
                return self::INTENT_EXACT_IDENTIFIER;
            }
        }

        foreach ($tokens as $token) {
            if (isset($synonyms[$token])) {
                continue;
            }
            if ($this->matchesVocabulary($token, 'brand')) {
                return self::INTENT_BRAND;
            }
        }

        foreach ($tokens as $token) {
            if ($this->matchesVocabulary($token, 'category')) {
                return self::INTENT_CATEGORY;
            }
        }

        if (preg_match('/\b(under|below|over|above|less|more|between|cheap|price)\b/', $normalized)) {
            return self::INTENT_PRICE;
        }

        return self::INTENT_BROAD;
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function synonymMap(string $locale): array
    {
        return Cache::remember('search.synonyms.'.$locale, 600, fn () => SearchSynonym::mapFor($locale));
    }

    /**
     * @return array<int, string>
     */
    protected function vocabulary(string $type): array
    {
        return Cache::remember('search.vocab.'.$type, 600, function () use ($type) {
            if ($type === 'brand') {
                return Brand::query()->pluck('slug')->all();
            }

            return Category::query()->pluck('slug')->all();
        });
    }

    protected function matchesVocabulary(string $token, string $type): bool
    {
        if (mb_strlen($token) < 3) {
            return false;
        }

        foreach ($this->vocabulary($type) as $slug) {
            $slug = (string) $slug;
            if ($slug !== '' && (str_contains($slug, $token) || str_contains($token, $slug))) {
                return true;
            }
        }

        return false;
    }
}
