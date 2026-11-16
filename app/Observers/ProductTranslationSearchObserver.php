<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\ProductTranslation;
use App\Services\Search\SearchIndexService;
use Illuminate\Support\Facades\Schema;

class ProductTranslationSearchObserver
{
    public function saved(ProductTranslation $translation): void
    {
        $this->refresh($translation->product_id);
    }

    public function deleted(ProductTranslation $translation): void
    {
        $this->refresh($translation->product_id);
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
