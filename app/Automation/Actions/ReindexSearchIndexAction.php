<?php

namespace App\Automation\Actions;

use App\Automation\AutomationAction;
use App\Models\AutomationRun;
use App\Services\Search\SearchIndexService;

class ReindexSearchIndexAction implements AutomationAction
{
    public function key(): string
    {
        return 'reindex_search_index';
    }

    public function label(): string
    {
        return 'Rebuild the product search index';
    }

    public function mutates(): bool
    {
        return true;
    }

    public function handle(array $input, AutomationRun $run): array
    {
        $service = app(SearchIndexService::class);

        if (! empty($input['product_ids']) && is_array($input['product_ids'])) {
            return ['documents' => $service->refreshMany(array_map('intval', $input['product_ids']))];
        }

        return ['documents' => $service->rebuild()];
    }
}
