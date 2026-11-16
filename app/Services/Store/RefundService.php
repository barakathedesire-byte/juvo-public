<?php

namespace App\Services\Store;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;

class RefundService
{
    /**
     * Total money actually collected for an order (settled payments only).
     */
    public function paidAmount(Order $order): float
    {
        return round((float) Payment::where('order_id', $order->id)
            ->where('status', 'completed')
            ->sum('amount'), 2);
    }

    /**
     * Execute a refund against an order that has collected money. Idempotent:
     * a refund already completed is never settled twice, and the refundable
     * amount is capped at what was actually paid.
     */
    public function settle(Refund $refund): Refund
    {
        return DB::transaction(function () use ($refund) {
            $locked = Refund::whereKey($refund->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === 'completed') {
                return $locked;
            }

            $order = $locked->order()->lockForUpdate()->first();

            if (! $order) {
                return $locked;
            }

            if ($order->payment_status === 'refunded' || $order->payment_status === 'partially_refunded') {
                return $locked;
            }

            $paid = $this->paidAmount($order);

            if ($paid <= 0) {
                return $locked;
            }

            $cap = round(min((float) $locked->amount, $paid), 2);
            $partial = $cap < $paid;

            $locked->update([
                'amount' => $cap,
                'status' => 'completed',
                'refund_id' => 'REF-'.strtoupper(bin2hex(random_bytes(5))),
                'response' => [
                    'settled' => true,
                    'amount' => $cap,
                    'paid' => $paid,
                    'partial' => $partial,
                ],
            ]);

            $order->update([
                'payment_status' => $partial ? 'partially_refunded' : 'refunded',
            ]);

            if (! $partial) {
                Payment::where('order_id', $order->id)
                    ->where('status', 'completed')
                    ->update(['status' => 'refunded']);
            }

            return $locked->refresh();
        });
    }
}
