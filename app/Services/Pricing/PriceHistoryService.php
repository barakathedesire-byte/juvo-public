<?php

namespace App\Services\Pricing;

use App\Models\PriceChangeLog;
use App\Models\Product;

/**
 * Append-only price history. Every stored price/discount change is recorded
 * with the actor, previous and new value, source and reason. Consumers use this
 * to show a true "previous price" and to audit who changed what.
 */
class PriceHistoryService
{
    public function record(
        int $productId,
        ?int $variantId,
        ?int $vendorId,
        string $field,
        float|string|null $oldValue,
        float|string|null $newValue,
        string $source = 'manual',
        ?string $reason = null,
        array $meta = []
    ): ?PriceChangeLog {
        if ($this->sameValue($oldValue, $newValue)) {
            return null;
        }

        [$actorType, $actorId] = $this->currentActor();

        return PriceChangeLog::create([
            'product_id' => $productId,
            'variant_id' => $variantId,
            'vendor_id' => $vendorId,
            'field' => $field,
            'old_value' => $oldValue === null ? null : round((float) $oldValue, 2),
            'new_value' => $newValue === null ? null : round((float) $newValue, 2),
            'source' => $source,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'reason' => $reason,
            'meta' => $meta ?: null,
        ]);
    }

    public function recordVariantChange(
        ?Product $product,
        int $variantId,
        string $field,
        float|string|null $oldValue,
        float|string|null $newValue,
        string $source = 'manual',
        ?string $reason = null,
        array $meta = []
    ): ?PriceChangeLog {
        return $this->record(
            $product?->id ?? 0,
            $variantId,
            $product?->vendor_id ? (int) $product->vendor_id : null,
            $field,
            $oldValue,
            $newValue,
            $source,
            $reason,
            $meta
        );
    }

    protected function sameValue(float|string|null $a, float|string|null $b): bool
    {
        if ($a === null && $b === null) {
            return true;
        }
        if ($a === null || $b === null) {
            return false;
        }

        return abs(round((float) $a, 2) - round((float) $b, 2)) < 0.005;
    }

    /**
     * @return array{0:?string,1:?int}
     */
    protected function currentActor(): array
    {
        if (auth()->guard('vendor')->check()) {
            return ['vendor', (int) auth()->guard('vendor')->id()];
        }
        if (auth()->guard('customer')->check()) {
            return ['customer', (int) auth()->guard('customer')->id()];
        }
        if (auth()->check()) {
            return ['staff', (int) auth()->id()];
        }

        return ['system', null];
    }
}
