<?php

namespace App\Services\Store;

use App\Exceptions\InsufficientStockException;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Services\Intelligence\WorkflowDispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryService
{
    /**
     * Decrement stock for a sale. Must be called inside a DB transaction so the
     * row lock is held until the surrounding order is committed.
     *
     * @throws InsufficientStockException
     */
    public function decrement(ProductVariant $variant, int $quantity, array $context = []): InventoryMovement
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity to decrement must be positive.');
        }

        $locked = $this->lock($variant);

        if ((int) $locked->stock < $quantity) {
            throw new InsufficientStockException($locked->id, $quantity, (int) $locked->stock);
        }

        $locked->stock = (int) $locked->stock - $quantity;
        $locked->save();

        $movement = $this->record($locked, 'sale', -$quantity, (int) $locked->stock, $context);

        $this->announceStockChange($locked);

        return $movement;
    }

    /**
     * Return stock (cancellations, returns, restock).
     */
    public function restock(ProductVariant $variant, int $quantity, string $type = 'return', array $context = []): InventoryMovement
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity to restock must be positive.');
        }

        $locked = $this->lock($variant);
        $locked->stock = (int) $locked->stock + $quantity;
        $locked->save();

        $movement = $this->record($locked, $type, $quantity, (int) $locked->stock, $context);

        $this->announceStockChange($locked);

        return $movement;
    }

    /**
     * Set an absolute stock level (vendor manual adjustment / correction).
     * Returns null when the level is unchanged.
     */
    public function adjust(ProductVariant $variant, int $newQuantity, ?string $reason = null, ?int $actorId = null, string $type = 'adjustment'): ?InventoryMovement
    {
        if ($newQuantity < 0) {
            throw new InvalidArgumentException('Stock level cannot be negative.');
        }

        $locked = $this->lock($variant);
        $delta = $newQuantity - (int) $locked->stock;

        if ($delta === 0) {
            return null;
        }

        $locked->stock = $newQuantity;
        $locked->save();

        $movement = $this->record($locked, $type, $delta, $newQuantity, [
            'reason' => $reason,
            'created_by' => $actorId,
        ]);

        $this->announceStockChange($locked);

        return $movement;
    }

    /**
     * Fire the stock_update automation trigger once the surrounding
     * transaction (if any) has committed, so a run never observes — or is
     * rolled back with — uncommitted stock.
     */
    protected function announceStockChange(ProductVariant $variant): void
    {
        $dispatch = function () use ($variant) {
            app(WorkflowDispatcher::class)->dispatchTrigger('stock_update', [
                'variant_id' => $variant->id,
                'product_id' => $variant->product_id,
                'stock' => (int) $variant->stock,
            ]);
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($dispatch);

            return;
        }

        $dispatch();
    }

    /**
     * Stock valuation (current stock x effective price) for a vendor, or the
     * whole catalog when $vendorId is null.
     */
    public function valuation(?int $vendorId = null): float
    {
        $query = ProductVariant::query();

        if ($vendorId !== null) {
            $query->whereHas('product', fn ($q) => $q->where('vendor_id', $vendorId));
        }

        return (float) $query->get()->sum(
            fn (ProductVariant $v) => (int) $v->stock * (float) ($v->discount_price ?? $v->price)
        );
    }

    /**
     * Variants at or below their low-stock threshold.
     */
    public function lowStock(?int $vendorId = null): Collection
    {
        $query = ProductVariant::with('product.translations')
            ->whereNotNull('low_stock_threshold')
            ->whereColumn('stock', '<=', 'low_stock_threshold');

        if ($vendorId !== null) {
            $query->whereHas('product', fn ($q) => $q->where('vendor_id', $vendorId));
        }

        return $query->orderBy('stock')->get();
    }

    public function movements(ProductVariant $variant, int $limit = 50): Collection
    {
        return InventoryMovement::where('variant_id', $variant->id)
            ->latest()
            ->limit($limit)
            ->get();
    }

    protected function lock(ProductVariant $variant): ProductVariant
    {
        return ProductVariant::whereKey($variant->id)->lockForUpdate()->firstOrFail();
    }

    protected function record(ProductVariant $variant, string $type, int $delta, int $balanceAfter, array $context = []): InventoryMovement
    {
        return InventoryMovement::create([
            'product_id' => $variant->product_id,
            'variant_id' => $variant->id,
            'type' => $type,
            'quantity' => $delta,
            'balance_after' => $balanceAfter,
            'reason' => $context['reason'] ?? null,
            'reference_type' => $context['reference_type'] ?? null,
            'reference_id' => $context['reference_id'] ?? null,
            'created_by' => $context['created_by'] ?? null,
        ]);
    }
}
