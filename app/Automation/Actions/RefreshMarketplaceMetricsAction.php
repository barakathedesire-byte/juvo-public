<?php

namespace App\Automation\Actions;

use App\Automation\AutomationAction;
use App\Models\AutomationRun;
use App\Services\Intelligence\MarketplaceIntelligenceService;

class RefreshMarketplaceMetricsAction implements AutomationAction
{
    public function key(): string
    {
        return 'refresh_marketplace_metrics';
    }

    public function label(): string
    {
        return 'Refresh marketplace metric snapshots';
    }

    public function mutates(): bool
    {
        return true;
    }

    public function handle(array $input, AutomationRun $run): array
    {
        $service = app(MarketplaceIntelligenceService::class);

        if (! empty($input['date'])) {
            return ['snapshots' => $service->snapshot(\Illuminate\Support\Carbon::parse($input['date']))];
        }

        return ['snapshots' => $service->backfill((int) ($input['days'] ?? 1))];
    }
}
