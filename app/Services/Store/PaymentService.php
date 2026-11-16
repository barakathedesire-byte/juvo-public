<?php

namespace App\Services\Store;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentGateway;

class PaymentService
{
    public function gatewayId(?string $code): ?int
    {
        if (! $code) {
            return null;
        }

        return PaymentGateway::where('code', $code)->value('id');
    }

    /**
     * Record an initial (pending) payment attempt for an order.
     */
    public function recordPending(Order $order, ?string $method, array $meta = []): Payment
    {
        return Payment::create([
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'gateway_id' => $this->gatewayId($method),
            'amount' => $order->total_amount,
            'currency' => $order->currency,
            'payment_method' => $method,
            'status' => 'pending',
            'meta' => $meta,
        ]);
    }

    /**
     * Mark the most recent payment for an order as completed and settle the
     * order. Idempotent: a payment that is already completed (or an order that
     * is already paid) is never double-applied by a repeated callback.
     */
    public function markCompleted(Order $order, array $response = [], ?string $transactionId = null): ?Payment
    {
        $payment = Payment::where('order_id', $order->id)->latest('id')->first();

        if ($payment && $payment->status === 'completed') {
            if ($order->payment_status !== 'paid') {
                $order->update(['payment_status' => 'paid']);
            }

            return $payment;
        }

        if ($payment) {
            $payment->update([
                'status' => 'completed',
                'transaction_id' => $transactionId ?? $payment->transaction_id,
                'response' => $response,
            ]);
        }

        $order->update(['payment_status' => 'paid']);

        return $payment;
    }

    /**
     * Record a failed attempt. Never downgrades an order that is already paid
     * (a late failure callback must not flip a settled order back).
     */
    public function markFailed(Order $order, array $response = [], ?string $transactionId = null): ?Payment
    {
        $payment = Payment::where('order_id', $order->id)->latest('id')->first();

        if ($order->payment_status === 'paid' || ($payment && $payment->status === 'completed')) {
            return $payment;
        }

        if ($payment) {
            $payment->update([
                'status' => 'failed',
                'transaction_id' => $transactionId ?? $payment->transaction_id,
                'response' => $response,
            ]);
        }

        $order->update(['payment_status' => 'failed']);

        return $payment;
    }
}
