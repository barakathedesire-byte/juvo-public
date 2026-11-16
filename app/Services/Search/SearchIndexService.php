<?php

namespace App\Services\Search;

use App\Models\Product;
use App\Models\ProductSearchDocument;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Builds and maintains the derived search index (`product_search_documents`).
 *
 * The index is always rebuildable from authoritative catalog tables and is
 * never consulted for eligibility, stock or price — those are re-checked
 * against `products`/`product_variants` at query time.
 */
class SearchIndexService
{
    public function __construct(
        protected SearchEligibility $eligibility,
    ) {}

    /**
     * Rebuild every document for a single product across its locales.
     */
    public function refreshProduct(Product $product): int
    {
        $product->loadMissing([
            'translations', 'brand.translations', 'category.translations', 'vendor',
            'variants', 'primaryVariant', 'attributeValues.translations',
        ]);

        ProductSearchDocument::where('product_id', $product->id)->delete();

        $variants = $product->variants;
        $priceMin = $variants->whereNotNull('price')->min('price');
        $discountPriceMin = $variants->whereNotNull('discount_price')->min('discount_price');

        $inStock = $this->eligibility->productIsPurchasable($product);

        $popularity = (int) ($this->popularityMap()[$product->id] ?? 0);
        [$rating, $reviewCount] = $this->reviewStats($product->id);

        $brandName = $product->brand?->translations->first()?->name;
        $categoryName = $product->category?->translations->first()?->name;
        $vendorName = $product->vendor?->shop_name ?? $product->vendor?->name;
        $attributes = $product->attributeValues
            ->flatMap(fn ($v) => [$v->value, $v->translated_value])
            ->filter()
            ->unique()
            ->implode(' ');

        $written = 0;

        foreach ($product->translations as $translation) {
            $locale = $translation->language_code ?: 'en';
            $title = trim((string) $translation->name);

            if ($title === '') {
                continue;
            }

            $keywords = collect([
                $title,
                $brandName,
                $categoryName,
                $vendorName,
                $product->product_type,
                $attributes,
                $translation->tags,
                $translation->short_description,
                Str::limit((string) $translation->description, 400, ''),
                $product->slug,
            ])->filter()->implode(' ');

            $keywords = $this->normalizeText($keywords);

            ProductSearchDocument::create([
                'product_id' => $product->id,
                'locale' => $locale,
                'vendor_id' => $product->vendor_id,
                'category_id' => $product->category_id,
                'brand_id' => $product->brand_id,
                'title' => $this->normalizeText($title),
                'keywords' => $keywords,
                'price_min' => $priceMin,
                'discount_price_min' => $discountPriceMin,
                'in_stock' => $inStock,
                'is_promoted' => (bool) $product->is_sponsored,
                'rating' => $rating,
                'review_count' => $reviewCount,
                'popularity' => $popularity,
                'meta' => ['sku' => $variants->first()?->SKU],
                'refreshed_at' => now(),
            ]);

            $written++;
        }

        return $written;
    }

    /**
     * Drop the index rows for a product that no longer exists / is hidden.
     */
    public function forgetProduct(int $productId): void
    {
        ProductSearchDocument::where('product_id', $productId)->delete();
    }

    /**
     * Refresh documents for products whose content likely changed.
     *
     * @param  array<int, int>  $productIds
     */
    public function refreshMany(array $productIds): int
    {
        $written = 0;
        foreach (array_unique($productIds) as $id) {
            $product = Product::find($id);
            $written += $product ? $this->refreshProduct($product) : 0;
        }

        return $written;
    }

    /**
     * Full rebuild. Chunked so it never loads the catalog into memory.
     */
    public function rebuild(?callable $progress = null): int
    {
        $written = 0;
        $count = 0;

        Product::query()->orderBy('id')->chunkById(200, function ($products) use (&$written, &$count, $progress) {
            foreach ($products as $product) {
                $written += $this->refreshProduct($product);
                $count++;
            }
            if ($progress) {
                $progress($count, $written);
            }
        });

        Cache::forget('search.index.rebuilt_at');
        Cache::put('search.index.rebuilt_at', now()->toIso8601String(), 86400);

        return $written;
    }

    public function lastRebuiltAt(): ?string
    {
        return Cache::get('search.index.rebuilt_at')
            ?? ProductSearchDocument::max('refreshed_at');
    }

    public function documentCount(): int
    {
        return ProductSearchDocument::count();
    }

    /**
     * Products missing an up-to-date document (index freshness signal).
     */
    public function staleCount(int $olderThanMinutes = 1440): int
    {
        return Product::whereDoesntHave('searchDocuments', function ($q) use ($olderThanMinutes) {
            $q->where('refreshed_at', '>=', now()->subMinutes($olderThanMinutes));
        })->count();
    }

    /* ------------------------------------------------------------------ */

    protected function normalizeText(string $value): string
    {
        $value = mb_strtolower($value);
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    protected function popularityMap(): \Illuminate\Support\Collection
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
     * @return array{0:float,1:int}
     */
    protected function reviewStats(int $productId): array
    {
        $row = DB::table('product_reviews')
            ->where('product_id', $productId)
            ->where('is_approved', true)
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as cnt')
            ->first();

        return [(float) ($row->avg ?? 0), (int) ($row->cnt ?? 0)];
    }
}
