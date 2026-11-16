<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Payment;
use App\Models\VendorLedgerEntry;
use App\Services\Pesapal\PesapalService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessPesapalWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 60;

    public function __construct(
        public string $orderTrackingId,
        public string $notificationId,
        public string $merchantReference
    ) {}

    public function handle(PesapalService $pesapal): void
    {
        Log::info('Processing Pesapal webhook', [
            'order_tracking_id' => $this->orderTrackingId,
            'notification_id' => $this->notificationId,
            'merchant_reference' => $this->merchantReference,
        ]);

        // Find the payment record
        $payment = Payment::where('merchant_reference', $this->merchantReference)
            ->orWhere('pesapal_transaction_id', $this->orderTrackingId)
            ->first();

        if (! $payment) {
            Log::warning('Pesapal webhook: Payment not found', [
                'merchant_reference' => $this->merchantReference,
                'order_tracking_id' => $this->orderTrackingId,
            ]);

            return;
        }

        // Skip if already processed
        if ($payment->status === 'completed') {
            Log::info('Pesapal webhook: Payment already completed', [
                'payment_id' => $payment->id,
            ]);

            return;
        }

        // Verify transaction status with Pesapal
        try {
            $transaction = $pesapal->getTransactionStatus($this->orderTrackingId);
        } catch (\Exception $e) {
            Log::error('Pesapal webhook: Failed to get transaction status', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            throw $e; // Re-throw to trigger retry
        }

        $paymentStatus = $transaction['payment_status'] ?? '';
        $paymentMethod = $transaction['payment_method'] ?? '';

        Log::info('Pesapal transaction status', [
            'payment_id' => $payment->id,
            'status' => $paymentStatus,
            'method' => $paymentMethod,
        ]);

        // Only process COMPLETED payments
        if ($paymentStatus !== 'COMPLETED') {
            $this->handleFailedPayment($payment, $paymentStatus);

            return;
        }

        // Process successful payment in a transaction
        DB::transaction(function () use ($payment, $transaction, $paymentMethod) {
            // Update payment
            $payment->update([
                'status' => 'completed',
                'transaction_id' => $this->orderTrackingId,
                'response' => $transaction,
                'meta' => array_merge($payment->meta ?? [], [
                    'verified_at' => now()->toISOString(),
                    'payment_method' => $paymentMethod,
                ]),
            ]);

            // Update order
            $order = $payment->order;
            if ($order) {
                $order->update([
                    'payment_status' => 'paid',
                    'status' => 'accepted', // Move from pending to accepted
                ]);

                // Create order status history
                $order->statusHistories()->create([
                    'status' => 'accepted',
                    'comment' => 'Payment verified via Pesapal (Tracking ID: '.$this->orderTrackingId.')',
                    'changed_by' => 'system',
                ]);
            }

            // Clear vendor ledger entries
            VendorLedgerEntry::where('payment_id', $payment->id)
                ->where('status', 'pending_settlement')
                ->update([
                    'status' => 'cleared',
                    'cleared_at' => now(),
                ]);

            Log::info('Pesapal payment completed successfully', [
                'payment_id' => $payment->id,
                'order_id' => $order->id ?? null,
            ]);
        });
    }

    protected function handleFailedPayment(Payment $payment, string $status): void
    {
        $failedStatuses = ['FAILED', 'INVALID', 'REVERSED', 'CANCELLED'];

        if (in_array(strtoupper($status), $failedStatuses)) {
            DB::transaction(function () use ($payment, $status) {
                $payment->update([
                    'status' => 'failed',
                    'response' => ['payment_status' => $status, 'failed_at' => now()->toISOString()],
                ]);

                $order = $payment->order;
                if ($order) {
                    $order->update(['payment_status' => 'failed']);
                }

                VendorLedgerEntry::where('payment_id', $payment->id)
                    ->where('status', 'pending_settlement')
                    ->update(['status' => 'failed']);
            });

            Log::warning('Pesapal payment failed', [
                'payment_id' => $payment->id,
                'status' => $status,
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Pesapal webhook job failed permanently', [
            'order_tracking_id' => $this->orderTrackingId,
            'notification_id' => $this->notificationId,
            'merchant_reference' => $this->merchantReference,
            'error' => $exception->getMessage(),
        ]);
    }
}
