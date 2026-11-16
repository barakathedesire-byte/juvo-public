<?php

namespace App\Services\Vendor;

use App\Models\Order;
use App\Models\Vendor;
use App\Models\VendorCommission;
use App\Models\VendorPayout;
use App\Models\VendorTransaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Single source of truth for vendor money.
 *
 * Commission lifecycle:
 *   pending   – order placed, not yet completed (not eligible for payout)
 *   available – order completed, eligible to be paid out
 *   settled   – paid to the vendor through a payout
 *   reversed  – order cancelled / refunded
 *
 * VendorTransaction rows form an immutable statement log (sale credits,
 * reversals and payouts). Balances themselves are always derived from the
 * commission rows so the two can never silently disagree.
 */
class VendorFinanceService
{
    /**
     * @return array{pending:float,available:float,paid:float,reversed:float,gross:float,commission:float,reserved:float,withdrawable:float,currency:string}
     */
    public function balances(Vendor $vendor): array
    {
        $row = VendorCommission::where('vendor_id', $vendor->id)
            ->selectRaw("
                SUM(CASE WHEN status = 'pending' THEN net_amount ELSE 0 END) AS pending,
                SUM(CASE WHEN status = 'available' THEN net_amount ELSE 0 END) AS available,
                SUM(CASE WHEN status = 'settled' THEN net_amount ELSE 0 END) AS paid,
                SUM(CASE WHEN status = 'reversed' THEN net_amount ELSE 0 END) AS reversed,
                SUM(gross_amount) AS gross,
                SUM(commission_amount) AS commission
            ")
            ->first();

        $pending = (float) ($row->pending ?? 0);
        $available = (float) ($row->available ?? 0);
        $paid = (float) ($row->paid ?? 0);

        // Manual admin adjustments (and any adjustment credit consumed by a
        // payout) form part of the withdrawable balance.
        $adjustments = (float) VendorTransaction::where('vendor_id', $vendor->id)
            ->where('type', 'adjustment')
            ->sum('amount');

        $available = round($available + $adjustments, 2);

        // Money already committed to an in-flight payout request cannot be
        // requested again.
        $reserved = (float) VendorPayout::where('vendor_id', $vendor->id)
            ->whereIn('status', ['requested', 'approved', 'processing'])
            ->sum('amount');

        return [
            'pending' => round($pending, 2),
            'available' => round($available, 2),
            'adjustments' => round($adjustments, 2),
            'paid' => round($paid, 2),
            'paid' => round($paid, 2),
            'reversed' => round((float) ($row->reversed ?? 0), 2),
            'gross' => round((float) ($row->gross ?? 0), 2),
            'commission' => round((float) ($row->commission ?? 0), 2),
            'reserved' => round($reserved, 2),
            'withdrawable' => round(max(0, $available - $reserved), 2),
            'currency' => $vendor->currency ?? getWebConfig('default_currency', 'TZS'),
        ];
    }

    public function minimumPayout(Vendor $vendor): float
    {
        if ($vendor->minimum_payout !== null) {
            return (float) $vendor->minimum_payout;
        }

        return (float) \App\Models\BusinessRule::number('minimum_payout', 0);
    }

    /**
     * Move an order's commissions from `pending` to `available` and credit the
     * statement log. Idempotent: each commission is credited at most once.
     */
    public function markAvailableForOrder(Order $order): void
    {
        if (! $order->vendor_id) {
            return;
        }

        DB::transaction(function () use ($order) {
            $commissions = VendorCommission::where('order_id', $order->id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->get();

            foreach ($commissions as $commission) {
                $commission->status = 'available';
                $commission->save();

                $this->credit($order->vendor_id, $commission, $order);
            }
        });
    }

    /**
     * Reverse an order's unsettled commissions (cancel / refund) and post the
     * matching debit for anything that had already been credited.
     */
    public function reverseForOrder(Order $order): void
    {
        if (! $order->vendor_id) {
            return;
        }

        DB::transaction(function () use ($order) {
            $commissions = VendorCommission::where('order_id', $order->id)
                ->whereIn('status', ['pending', 'available'])
                ->lockForUpdate()
                ->get();

            foreach ($commissions as $commission) {
                $wasCredited = $commission->status === 'available';
                $commission->status = 'reversed';
                $commission->save();

                if ($wasCredited) {
                    $this->post(
                        $order->vendor_id,
                        'reversal',
                        -1 * (float) $commission->net_amount,
                        $order->id,
                        'commission:'.$commission->id.':reversal',
                        'Reversed earnings for order #'.$order->id
                    );
                }
            }
        });
    }

    /**
     * Settle `available` commissions against a paid payout (oldest first),
     * link them to the payout for audit, and post the payout debit.
     * Safe to call once per payout — a paid payout is never re-settled.
     */
    public function settleForPayout(VendorPayout $payout): float
    {
        return DB::transaction(function () use ($payout) {
            $remaining = (float) $payout->amount;
            $settled = 0.0;

            $commissions = VendorCommission::where('vendor_id', $payout->vendor_id)
                ->where('status', 'available')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($commissions as $commission) {
                if ($remaining <= 0) {
                    break;
                }

                $commission->status = 'settled';
                $commission->payout_id = $payout->id;
                $commission->save();

                $remaining = round($remaining - (float) $commission->net_amount, 2);
                $settled = round($settled + (float) $commission->net_amount, 2);
            }

            if ($remaining > 0) {
                $this->post(
                    $payout->vendor_id,
                    'adjustment',
                    -1 * $remaining,
                    null,
                    'payout-adjust:'.$payout->id,
                    'Adjustment credit applied to payout '.$payout->reference
                );
                $settled = round($settled + $remaining, 2);
            }

            $this->post(
                $payout->vendor_id,
                'payout',
                -1 * (float) $payout->amount,
                null,
                'payout:'.$payout->id,
                'Payout '.$payout->reference
            );

            return $settled;
        });
    }

    /**
     * Vendor-initiated payout request. Enforces the minimum payout and never
     * lets a vendor request more than their withdrawable balance.
     *
     * @throws RuntimeException
     */
    public function requestPayout(Vendor $vendor, float $amount, ?int $payoutMethodId = null, ?string $method = null): VendorPayout
    {
        $balances = $this->balances($vendor);

        if ($amount <= 0) {
            throw new RuntimeException('Enter a payout amount greater than zero.');
        }

        $minimum = $this->minimumPayout($vendor);
        if ($minimum > 0 && $amount < $minimum) {
            throw new RuntimeException('The minimum payout amount is '.number_format($minimum, 2).'.');
        }

        if ($amount > $balances['withdrawable']) {
            throw new RuntimeException('Requested amount exceeds your withdrawable balance of '.number_format($balances['withdrawable'], 2).'.');
        }

        return DB::transaction(function () use ($vendor, $amount, $payoutMethodId, $method) {
            return VendorPayout::create([
                'vendor_id' => $vendor->id,
                'reference' => 'PAY-'.strtoupper(bin2hex(random_bytes(5))),
                'amount' => round($amount, 2),
                'currency' => $vendor->currency ?? getWebConfig('default_currency', 'TZS'),
                'method' => $method ?? 'manual',
                'payout_method_id' => $payoutMethodId,
                'status' => 'requested',
                'requested_at' => now(),
            ]);
        });
    }

    /**
     * Admin manual adjustment (audited). Positive credits the vendor's
     * statement; negative debits it. Does not touch commission rows.
     *
     * @throws RuntimeException
     */
    public function adjust(Vendor $vendor, float $amount, string $reason, ?int $actorId = null): VendorTransaction
    {
        if ($amount == 0.0) {
            throw new RuntimeException('Adjustment amount cannot be zero.');
        }
        if (trim($reason) === '') {
            throw new RuntimeException('A reason is required for manual adjustments.');
        }

        return $this->post(
            $vendor->id,
            'adjustment',
            round($amount, 2),
            null,
            'adjustment:'.bin2hex(random_bytes(6)),
            'Manual adjustment: '.$reason,
            $actorId
        );
    }

    protected function credit(int $vendorId, VendorCommission $commission, Order $order): void
    {
        $this->post(
            $vendorId,
            'sale',
            (float) $commission->net_amount,
            $order->id,
            'commission:'.$commission->id,
            'Earnings for order #'.$order->id
        );
    }

    /**
     * Append a statement line with a running available-balance snapshot and an
     * idempotency guard on the reference.
     */
    protected function post(int $vendorId, string $type, float $amount, ?int $orderId, string $reference, ?string $description = null, ?int $actorId = null): VendorTransaction
    {
        $existing = VendorTransaction::where('vendor_id', $vendorId)
            ->where('reference', $reference)
            ->first();

        if ($existing) {
            return $existing;
        }

        $vendor = Vendor::find($vendorId);
        $balanceAfter = $vendor ? $this->balances($vendor)['available'] : 0;

        return VendorTransaction::create([
            'vendor_id' => $vendorId,
            'type' => $type,
            'amount' => round($amount, 2),
            'currency' => $vendor->currency ?? getWebConfig('default_currency', 'TZS'),
            'balance_after' => $balanceAfter,
            'order_id' => $orderId,
            'reference' => $reference,
            'description' => $description,
            'created_by' => $actorId,
        ]);
    }

    public function transactionsQuery(Vendor $vendor)
    {
        return VendorTransaction::where('vendor_id', $vendor->id)->latest();
    }

    public function commissionsQuery(Vendor $vendor)
    {
        return VendorCommission::with('order')->where('vendor_id', $vendor->id)->latest();
    }

    public function payoutsQuery(Vendor $vendor)
    {
        return VendorPayout::with('payoutMethod')->where('vendor_id', $vendor->id)->latest();
    }
}
