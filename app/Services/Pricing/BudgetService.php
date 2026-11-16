<?php

namespace App\Services\Pricing;

use App\Models\Promotion;
use App\Models\PromotionReservation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Atomic budget reservations for funded promotions.
 *
 * A promotion with a `budget_amount` must never be over-spent or double-spent.
 * Reserving takes a row lock, accounts for both committed spend and other live
 * holds, and refuses if the amount does not fit. Committing converts a hold
 * into real spend (and flips the promotion to `exhausted` when drained);
 * releasing gives the budget back. The unique `reference` makes every call
 * idempotent.
 */
class BudgetService
{
    public function reserve(Promotion $promotion, float $amount, string $reference, ?int $orderId = null, ?Carbon $expiresAt = null): ?PromotionReservation
    {
        $amount = round($amount, 2);
        if ($amount <= 0 || $promotion->budget_amount === null) {
            return null;
        }

        return DB::transaction(function () use ($promotion, $amount, $reference, $orderId, $expiresAt) {
            $existing = PromotionReservation::where('reference', $reference)->first();
            if ($existing) {
                return $existing;
            }

            /** @var Promotion|null $fresh */
            $fresh = Promotion::whereKey($promotion->id)->lockForUpdate()->first();
            if (! $fresh) {
                return null;
            }

            $held = (float) PromotionReservation::where('promotion_id', $fresh->id)
                ->where('status', 'held')
                ->sum('amount');

            $available = (float) $fresh->budget_amount - (float) $fresh->budget_spent - $held;

            if ($amount - $available > 0.0001) {
                return null;
            }

            return PromotionReservation::create([
                'promotion_id' => $fresh->id,
                'order_id' => $orderId,
                'reference' => $reference,
                'amount' => $amount,
                'status' => 'held',
                'expires_at' => $expiresAt ?? now()->addMinutes(30),
            ]);
        });
    }

    /**
     * Commit a held reservation and charge it against the promotion budget.
     */
    public function commit(string $reference): void
    {
        DB::transaction(function () use ($reference) {
            $reservation = PromotionReservation::where('reference', $reference)->lockForUpdate()->first();
            if (! $reservation || $reservation->status !== 'held') {
                return;
            }

            $promotion = Promotion::whereKey($reservation->promotion_id)->lockForUpdate()->first();
            if ($promotion) {
                $promotion->budget_spent = round((float) $promotion->budget_spent + (float) $reservation->amount, 2);
                if ($promotion->budget_amount !== null && $promotion->budget_spent >= (float) $promotion->budget_amount) {
                    $promotion->status = 'exhausted';
                }
                $promotion->save();
            }

            $reservation->update(['status' => 'committed']);
        });
    }

    public function release(string $reference): void
    {
        PromotionReservation::where('reference', $reference)
            ->where('status', 'held')
            ->update(['status' => 'released']);
    }

    public function expireStale(): int
    {
        return PromotionReservation::where('status', 'held')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => 'released']);
    }
}
