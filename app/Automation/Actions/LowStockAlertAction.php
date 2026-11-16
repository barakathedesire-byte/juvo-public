<?php

namespace App\Automation\Actions;

use App\Automation\AutomationAction;
use App\Models\AutomationRun;
use App\Models\ProductVariant;
use App\Services\Admin\AuditLogger;
use App\Services\Store\InventoryService;

/**
 * Flag every variant that has dropped to or below its low-stock threshold.
 *
 * Read-only over inventory: it never mutates stock, so it can never fight
 * with an order or a restock that is running concurrently. The alert is
 * persisted on the run (structured output) and written to the audit log per
 * affected vendor so it surfaces in the admin trail.
 */
class LowStockAlertAction implements AutomationAction
{
    public function key(): string
    {
        return 'low_stock_alert';
    }

    public function label(): string
    {
        return 'Low stock alert';
    }

    public function mutates(): bool
    {
        return false;
    }

    public function handle(array $input, AutomationRun $run): array
    {
        $variants = app(InventoryService::class)->lowStock();

        $byVendor = $variants
            ->groupBy(fn (ProductVariant $variant) => (int) $variant->product?->vendor_id)
            ->map(fn ($group) => $group->count());

        foreach ($byVendor as $vendorId => $count) {
            AuditLogger::record(
                'inventory.low_stock',
                null,
                [],
                ['vendor_id' => $vendorId, 'variants_below_threshold' => $count]
            );
        }

        return [
            'ok' => true,
            'outcome' => 'flagged',
            'count' => $variants->count(),
            'vendors_affected' => $byVendor->count(),
            'variants' => $variants->map(fn (ProductVariant $variant) => [
                'id' => $variant->id,
                'sku' => $variant->SKU ?? null,
                'stock' => (int) $variant->stock,
                'threshold' => (int) $variant->low_stock_threshold,
                'product_id' => $variant->product?->id,
                'vendor_id' => $variant->product?->vendor_id,
            ])->values()->all(),
        ];
    }
}
