<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\Search\SearchIndexService;
use Illuminate\Support\Facades\Schema;

class ProductSearchObserver
{
    public function saved(Product $product): void
    {
        if (! Schema::hasTable('product_search_documents')) {
            return;
        }

        if ($product->status == 1 && $product->approval_status === 'approved' && $product->visibility === 'public') {
            app(SearchIndexService::class)->refreshProduct($product);
        } else {
            app(SearchIndexService::class)->forgetProduct($product->id);
        }
    }

    public function deleted(Product $product): void
    {
        if (Schema::hasTable('product_search_documents')) {
            app(SearchIndexService::class)->forgetProduct($product->id);
        }
    }
}
