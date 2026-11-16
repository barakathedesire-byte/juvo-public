<?php

namespace App\Services\Vendor;

use App\Models\Order;
use App\Models\Vendor;
use App\Models\VendorCommission;

/**
 * Records per-order vendor commissions and keeps the order's aggregate
 * commission/net columns in sync. Commissions start `pending` and are
 * settled when an admin marks them paid (see Admin\PayoutController).
 */
class CommissionService
{
    /**
     * Create (or refresh) commission rows for every line of an order.
     */
    public function recordForOrder(Order $order): void
    {
        if (! $order->vendor_id) {
            return;
        }

        $vendor = Vendor::find($order->vendor_id);
        if (! $vendor) {
            return;
        }

        $rate = $vendor->effectiveCommissionRate();

        $order->loadMissing('details');

        if ($order->details->isEmpty()) {
            return;
        }

        $commissionTotal = 0.0;
        $netTotal = 0.0;

        foreach ($order->details as $detail) {
            $gross = round((float) $detail->price * (int) $detail->quantity, 2);
            $commission = round($gross * ($rate / 100), 2);
            $net = round($gross - $commission, 2);

            VendorCommission::updateOrCreate(
                ['order_detail_id' => $detail->id],
                [
                    'vendor_id' => $order->vendor_id,
                    'order_id' => $order->id,
                    'gross_amount' => $gross,
                    'commission_rate' => $rate,
                    'commission_amount' => $commission,
                    'platform_fee' => $commission,
                    'net_amount' => $net,
                    'currency' => $order->currency,
                    'status' => 'pending',
                ]
            );

            $commissionTotal += $commission;
            $netTotal += $net;
        }

        $order->update([
            'commission_total' => round($commissionTotal, 2),
            'net_vendor_amount' => round($netTotal, 2),
        ]);
    }

    /**
     * Reverse commissions for an order (e.g. cancelled/refunded).
     */
    public function reverseForOrder(Order $order): void
    {
        VendorCommission::where('order_id', $order->id)
            ->where('status', '!=', 'reversed')
            ->update(['status' => 'reversed']);
    }

    /**
     * Sum of unpaid (pending) net earnings for a vendor.
     */
    public function payableForVendor(Vendor $vendor): float
    {
        // Backward compatible helper; prefer VendorFinanceService->balances().
        return (float) VendorCommission::where('vendor_id', $vendor->id)
            ->where('status', 'available')
            ->sum('net_amount');
    }

    /**
     * Mark the given commission ids settled (used when a payout is paid).
     */
    public function settle(array $commissionIds): int
    {
        if (empty($commissionIds)) {
            return 0;
        }

        return VendorCommission::whereIn('id', $commissionIds)
            ->where('status', 'pending')
            ->update(['status' => 'settled']);
    }

    public function pendingCommissionIdsForVendor(Vendor $vendor): array
    {
        return VendorCommission::where('vendor_id', $vendor->id)
            ->where('status', 'available')
            ->pluck('id')
            ->all();
    }
}
