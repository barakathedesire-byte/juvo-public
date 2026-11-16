<?php

namespace App\Automation\Actions;

use App\Automation\AutomationAction;
use App\Models\AutomationRun;
use App\Services\Intelligence\ProductAssociationService;

class RecomputeAssociationsAction implements AutomationAction
{
    public function key(): string
    {
        return 'recompute_associations';
    }

    public function label(): string
    {
        return 'Recompute product associations';
    }

    public function mutates(): bool
    {
        return true;
    }

    public function handle(array $input, AutomationRun $run): array
    {
        return app(ProductAssociationService::class)->recompute((int) ($input['min_support'] ?? 2));
    }
}
