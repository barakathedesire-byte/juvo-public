<?php

namespace App\Http\Controllers\Store\PaymentGateway;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Vendor;
use App\Services\Pesapal\PesapalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PesapalController extends Controller
{
    public function __construct(protected PesapalService $pesapal) {}

    /**
     * Initiate payment with Pesapal for the given order.
     * Creates a pending payment record and vendor ledger entries,
     * then redirects to Pesapal payment page.
     */
    public function initiatePayment(Request $request, Order $order)
    {
        // Verify order belongs to authenticated customer
        $customer = Auth::guard('customer')->user();

        if (! $customer || $order->customer_id !== $customer->id) {
            abort(403, 'Unauthorized');
        }

        // Only allow payment for pending orders
        if (! in_array($order->status, ['pending', 'accepted'])) {
            return back()->with('error', 'This order cannot be paid for at this time.');
        }

        if ($order->payment_status === 'paid') {
            return back()->with('error', 'This order has already been paid.');
        }

        try {
            return DB::transaction(function () use ($order, $customer) {
                // Get or create payment gateway for Pesapal
                $gateway = \App\Models\PaymentGateway::where('code', 'pesapal')
                    ->where('is_active', true)
                    ->first();

                if (! $gateway) {
                    throw new \RuntimeException('Pesapal gateway is not configured or inactive');
                }

                // Generate unique merchant reference
                $merchantReference = 'ORD-'.$order->id.'-'.Str::random(8);

                // Create pending payment record
                $payment = Payment::create([
                    'order_id' => $order->id,
                    'customer_id' => $customer->id,
                    'gateway_id' => $gateway->id,
                    'merchant_reference' => $merchantReference,
                    'amount' => $order->total_amount,
                    'currency' => $order->currency,
                    'payment_method' => 'pesapal',
                    'status' => 'pending',
                    'meta' => [
                        'gateway' => 'pesapal',
                        'environment' => config('pesapal.environment'),
                    ],
                ]);

                // Create vendor ledger entries for each vendor in the order
                $this->createVendorLedgerEntries($payment, $order);

                // Prepare Pesapal order data
                $ipnUrl = route('pesapal.webhook');
                $notificationId = config('pesapal.ipn.notification_id');

                if (empty($notificationId)) {
                    throw new \RuntimeException('Pesapal IPN is not configured. Run php artisan pesapal:register-ipn');
                }

                $orderData = [
                    'merchant_reference' => $merchantReference,
                    'amount' => (float) $order->total_amount,
                    'currency' => $order->currency ?? 'KES',
                    'description' => 'Order #'.$order->id.' - '.($order->details->first()?->product_name ?? 'Products'),
                    'callback_url' => $ipnUrl,
                    'return_url' => route('pesapal.return', ['order' => $order->id]),
                    'cancel_url' => route('pesapal.cancel', ['order' => $order->id]),
                    'notification_id' => $notificationId,
                    'email' => $customer->email,
                    'phone' => $customer->phone ?? '',
                    'first_name' => $customer->name,
                    'last_name' => '',
                    'address' => $order->shippingAddress?->address ?? '',
                    'city' => $order->shippingAddress?->city ?? '',
                    'state' => $order->shippingAddress?->state ?? '',
                    'postal_code' => $order->shippingAddress?->postal_code ?? '',
                    'country_code' => $order->shippingAddress?->country ?? 'KE',
                ];

                // Submit order to Pesapal
                $response = $this->pesapal->submitOrder($orderData);

                // Update payment with Pesapal tracking ID
                $payment->update([
                    'pesapal_transaction_id' => $response['order_tracking_id'],
                    'ipn_id' => $notificationId,
                    'meta' => array_merge($payment->meta ?? [], [
                        'pesapal_response' => $response,
                        'submitted_at' => now()->toISOString(),
                    ]),
                ]);

                // Redirect to Pesapal payment page
                return redirect()->away($response['redirect_url']);
            });
        } catch (\Exception $e) {
            Log::error('Pesapal payment initiation failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to initiate payment: '.$e->getMessage());
        }
    }

    /**
     * Create vendor ledger entries for the order.
     */
    protected function createVendorLedgerEntries(Payment $payment, Order $order): void
    {
        $vendorIds = $order->details()
            ->with('product.vendor')
            ->get()
            ->pluck('product.vendor_id')
            ->filter()
            ->unique();

        foreach ($vendorIds as $vendorId) {
            $vendor = Vendor::find($vendorId);
            if (! $vendor) {
                continue;
            }

            // Calculate vendor's share of this order
            $vendorOrderTotal = $order->details()
                ->whereHas('product', fn ($q) => $q->where('vendor_id', $vendorId))
                ->sum('total');

            $commissionRate = $vendor->effectiveCommissionRate();
            $commissionAmount = round($vendorOrderTotal * ($commissionRate / 100), 2);
            $netEarning = round($vendorOrderTotal - $commissionAmount, 2);

            \App\Models\VendorLedgerEntry::create([
                'payment_id' => $payment->id,
                'vendor_id' => $vendorId,
                'order_id' => $order->id,
                'gross_amount' => $vendorOrderTotal,
                'marketplace_commission' => $commissionAmount,
                'net_vendor_earning' => $netEarning,
                'status' => 'pending_settlement',
                'commission_breakdown' => [
                    'rate' => $commissionRate,
                    'calculated_at' => now()->toISOString(),
                ],
            ]);
        }
    }

    /**
     * Return URL after successful payment.
     */
    public function return(Request $request, Order $order)
    {
        $this->authorizeOrderOwner($order);

        $trackingId = $request->query('OrderTrackingId');
        $merchantReference = $request->query('OrderMerchantReference');

        if (! $trackingId) {
            return redirect()->route('customer.orders.show', $order)
                ->with('error', 'Payment verification failed. Please contact support.');
        }

        // Verify transaction status
        try {
            $transaction = $this->pesapal->getTransactionStatus($trackingId);

            if (($transaction['payment_status'] ?? '') === 'COMPLETED') {
                return redirect()->route('customer.orders.show', $order)
                    ->with('success', 'Payment completed successfully!');
            }

            return redirect()->route('customer.orders.show', $order)
                ->with('warning', 'Payment is still processing. Please check back shortly.');
        } catch (\Exception $e) {
            Log::error('Pesapal return verification failed', [
                'order_id' => $order->id,
                'tracking_id' => $trackingId,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('customer.orders.show', $order)
                ->with('error', 'Unable to verify payment status. Please contact support.');
        }
    }

    /**
     * Cancel URL if user cancels payment.
     */
    public function cancel(Request $request, Order $order)
    {
        $this->authorizeOrderOwner($order);

        $payment = Payment::where('order_id', $order->id)
            ->where('payment_method', 'pesapal')
            ->latest()
            ->first();

        if ($payment && $payment->status === 'pending') {
            $payment->update(['status' => 'failed']);

            // Mark vendor ledger entries as failed
            \App\Models\VendorLedgerEntry::where('payment_id', $payment->id)
                ->update(['status' => 'failed']);
        }

        return redirect()->route('customer.orders.show', $order)
            ->with('error', 'Payment was cancelled. You can try again.');
    }

    /**
     * Payment callbacks arrive as ordinary authenticated browser requests, so
     * the order in the URL must still belong to the signed-in customer.
     */
    protected function authorizeOrderOwner(Order $order): void
    {
        $customer = Auth::guard('customer')->user();

        abort_unless($customer && (int) $order->customer_id === (int) $customer->id, 403);
    }
}
