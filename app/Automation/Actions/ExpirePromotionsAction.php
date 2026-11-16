<?php

namespace App\Automation\Actions;

use App\Automation\AutomationAction;
use App\Models\AutomationRun;
use App\Models\Promotion;
use App\Services\Pricing\BudgetService;

/**
 * Advance promotion lifecycles: activate scheduled promotions that have
 * started, expire ones that have ended, and release stale budget holds.
 */
class ExpirePromotionsAction implements AutomationAction
{
    public function key(): string
    {
        return 'expire_promotions';
    }

    public function label(): string
    {
        return 'Advance & expire promotions';
    }

    public function mutates(): bool
    {
        return true;
    }

    public function handle(array $input, AutomationRun $run): array
    {
        $now = now();

        $activated = Promotion::where('status', 'scheduled')
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->update(['status' => 'active', 'active' => true]);

        $expired = Promotion::whereIn('status', ['active', 'scheduled', 'paused'])
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', $now)
            ->update(['status' => 'expired', 'active' => false]);

        $released = app(BudgetService::class)->expireStale();

        return [
            'activated' => $activated,
            'expired' => $expired,
            'reservations_released' => $released,
        ];
    }
}
