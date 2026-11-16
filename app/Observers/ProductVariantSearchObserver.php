<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Search\SearchIndexService;
use Illuminate\Support\Facades\Schema;

class ProductVariantSearchObserver
{
    public function saved(ProductVariant $variant): void
    {
        $this->refresh($variant->product_id);
    }

    public function deleted(ProductVariant $variant): void
    {
        $this->refresh($variant->product_id);
    }

    protected function refresh(?int $productId): void
    {
        if (! $productId || ! Schema::hasTable('product_search_documents')) {
            return;
        }

        $product = Product::find($productId);

        if ($product) {
            app(SearchIndexService::class)->refreshProduct($product);
        }
    }
}
