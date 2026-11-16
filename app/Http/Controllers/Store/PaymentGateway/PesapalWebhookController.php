<?php

namespace App\Http\Controllers\Store\PaymentGateway;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPesapalWebhook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PesapalWebhookController extends Controller
{
    /**
     * Handle Pesapal IPN (Instant Payment Notification).
     * This endpoint receives callbacks from Pesapal when payment status changes.
     *
     * Pesapal v3 sends:
     * - OrderTrackingId: The tracking ID from SubmitOrderRequest
     * - OrderMerchantReference: Your merchant reference
     * - OrderNotificationType: IPN type
     */
    public function handle(Request $request)
    {
        $orderTrackingId = $request->query('OrderTrackingId');
        $merchantReference = $request->query('OrderMerchantReference');
        $notificationId = $request->query('OrderNotificationId') ?? config('pesapal.ipn.notification_id');

        Log::info('Pesapal IPN received', [
            'order_tracking_id' => $orderTrackingId,
            'merchant_reference' => $merchantReference,
            'notification_id' => $notificationId,
            'query' => $request->query(),
        ]);

        // Validate required parameters
        if (empty($orderTrackingId) || empty($merchantReference)) {
            Log::warning('Pesapal IPN missing required parameters', [
                'query' => $request->query(),
            ]);

            return response('Missing required parameters', 400);
        }

        // Verify the notification_id matches our registered IPN
        $configuredNotificationId = config('pesapal.ipn.notification_id');
        if ($configuredNotificationId && $notificationId !== $configuredNotificationId) {
            Log::warning('Pesapal IPN notification_id mismatch', [
                'received' => $notificationId,
                'expected' => $configuredNotificationId,
            ]);

            return response('Invalid notification id', 403);
        }

        // Dispatch job for async processing
        ProcessPesapalWebhook::dispatch(
            $orderTrackingId,
            $notificationId,
            $merchantReference
        );

        // Respond immediately to Pesapal
        return response('OK', 200);
    }
}
