<?php

namespace App\Services\Search;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Single authoritative eligibility gate for search. Reuses the storefront
 * `visible()` scope (status, approval, visibility, archived, published window,
 * approved vendor) and adds the catalog's real purchase eligibility — a
 * product needs at least one purchasable variant, never a blanket
 * "products.stock > 0" rule (that column does not exist and is not the source
 * of truth for stock).
 *
 * Eligibility is always applied server-side; autocomplete, facets, counts and
 * pagination all flow through here so restricted products cannot leak.
 */
class SearchEligibility
{
    /**
     * True when a product has at least one variant that can be purchased.
     */
    public function productIsPurchasable(Product $product): bool
    {
        $variants = $product->relationLoaded('variants') ? $product->variants : $product->variants()->get();

        if ($variants->isEmpty()) {
            return (int) ($product->primaryVariant?->stock ?? 0) > 0;
        }

        return $variants->contains(fn ($variant) => $this->variantPurchasable($variant));
    }

    public function variantPurchasable($variant): bool
    {
        return (int) ($variant->stock ?? 0) > 0;
    }

    /**
     * Apply the eligibility scope to a products query.
     */
    public function scope(Builder $query, array $config = []): Builder
    {
        $query->visible();

        if ($this->requiresStock($config)) {
            $query->whereHas('variants', fn ($q) => $q->where('stock', '>', 0));
        }

        return $query;
    }

    /**
     * Filter an already-loaded product collection by eligibility.
     */
    public function filter(Collection $products, array $config = []): Collection
    {
        if (! $this->requiresStock($config)) {
            return $products->values();
        }

        return $products
            ->filter(fn (Product $product) => $this->productIsPurchasable($product))
            ->values();
    }

    public function requiresStock(array $config): bool
    {
        return (bool) ($config['require_in_stock'] ?? true);
    }
}
