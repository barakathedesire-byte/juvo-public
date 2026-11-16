<?php

namespace App\Http\Controllers\Api\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Logistics\JuvoLogisticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FulfillmentController extends Controller
{
    public function __construct(protected JuvoLogisticsService $logistics) {}

    /**
     * Initiate fulfillment for an order.
     */
    public function initiate(Request $request, Order $order)
    {
        $this->authorizeVendorOrder($request, $order);

        if ($order->isPickup()) {
            return response()->json([
                'success' => false,
                'message' => 'Pickup orders do not require logistics delivery fulfillment',
            ], 422);
        }

        // Verify order can be fulfilled
        if (! in_array($order->status, ['accepted', 'processing', 'packed'])) {
            return response()->json([
                'success' => false,
                'message' => 'Order cannot be fulfilled at this stage. Current status: ' . $order->status,
            ], 422);
        }

        $isCashOnDelivery = in_array(strtolower((string) $order->payment_method), [
            'cod',
            'payondelivery',
            'pod',
            'pay_on_delivery',
        ], true);

        if ($order->payment_status !== 'paid' && ! ($isCashOnDelivery && config('juvo-logistics.fleet.cod_enabled'))) {
            return response()->json([
                'success' => false,
                'message' => 'Order payment must be completed before fulfillment',
            ], 422);
        }

        try {
            $result = $this->logistics->createDeliveryOrder($order);

            // Update order status
            $order->update([
                'status' => 'dispatched',
                'fulfilled_at' => now(),
            ]);

            $order->statusHistories()->create([
                'status' => 'dispatched',
                'comment' => 'Order dispatched via Juvo Logistics (Tracking: ' . ($result['id'] ?? 'N/A') . ')',
                'changed_by' => 'system',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Fulfillment initiated successfully',
                'tracking' => [
                    'tracking_id' => $result['id'] ?? null,
                    'carrier' => $result['carrier'] ?? null,
                    'status' => $result['status'] ?? 'pending',
                    'tracking_url' => $this->logistics->getTrackingUrl(
                        $result['id'] ?? '',
                        $result['trackingToken'] ?? $result['tracking_token'] ?? null,
                    ),
                    'embed_url' => $this->logistics->getEmbedTrackingUrl(
                        $result['id'] ?? '',
                        $result['trackingToken'] ?? $result['tracking_token'] ?? null,
                    ),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to initiate fulfillment', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate fulfillment: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get fulfillment status for an order.
     */
    public function status(Request $request, Order $order)
    {
        $this->authorizeVendorOrder($request, $order);

        if (! $order->logistics_tracking_id) {
            return response()->json([
                'success' => false,
                'message' => 'No logistics tracking ID found for this order',
            ], 404);
        }

        $status = $this->logistics->getDeliveryStatus($order);

        if (! $status) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to retrieve status from logistics provider',
            ], 503);
        }

        return response()->json([
            'success' => true,
            'tracking' => [
                'tracking_id' => $order->logistics_tracking_id,
                'status' => $status['status'] ?? 'unknown',
                'driver' => $status['driver'] ?? null,
                'estimated_delivery' => $status['estimated_delivery'] ?? null,
                'tracking_url' => $this->logistics->getTrackingUrl(
                    $order->logistics_tracking_id,
                    $status['trackingToken'] ?? $status['tracking_token'] ?? data_get($order->logistics_data, 'trackingToken'),
                ),
                'embed_url' => $this->logistics->getEmbedTrackingUrl(
                    $order->logistics_tracking_id,
                    $status['trackingToken'] ?? $status['tracking_token'] ?? data_get($order->logistics_data, 'trackingToken'),
                ),
                'proof_of_delivery' => [
                    'photo' => $order->pod_photo_url ?? null,
                    'signature' => $order->pod_signature_url ?? null,
                    'otp_verified' => $order->pod_otp_verified ?? false,
                    'delivered_at' => $order->pod_delivered_at ?? null,
                ],
            ],
        ]);
    }

    /**
     * Cancel fulfillment for an order.
     */
    public function cancel(Request $request, Order $order)
    {
        $this->authorizeVendorOrder($request, $order);

        if (! $order->logistics_tracking_id) {
            return response()->json([
                'success' => false,
                'message' => 'No active fulfillment to cancel',
            ], 422);
        }

        $reason = $request->input('reason', 'Cancelled by merchant');

        if (in_array($order->status, ['delivered', 'completed', 'canceled'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'A completed or already cancelled order cannot be cancelled',
            ], 422);
        }

        $cancelled = $this->logistics->cancelDeliveryOrder($order, $reason);

        if ($cancelled) {
            $order->update([
                'status' => 'canceled',
                'canceled_at' => now(),
                'cancel_reason' => $reason,
            ]);
            $order->statusHistories()->create([
                'status' => 'canceled',
                'comment' => $reason,
                'changed_by' => 'vendor',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Fulfillment cancelled',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to cancel fulfillment',
        ], 500);
    }

    /**
     * Get tracking embed HTML for iframe.
     */
    public function embed(Request $request, Order $order)
    {
        $this->authorizeVendorOrder($request, $order);

        if (! $order->logistics_tracking_id) {
            return response()->json([
                'success' => false,
                'message' => 'No tracking available',
            ], 404);
        }

        $embedUrl = $this->logistics->getEmbedTrackingUrl(
            $order->logistics_tracking_id,
            data_get($order->logistics_data, 'trackingToken'),
        );

        return response()->json([
            'success' => true,
            'embed_url' => $embedUrl,
            'iframe_html' => '<iframe src="' . $embedUrl . '" width="100%" height="500" frameborder="0" style="border-radius: 8px;"></iframe>',
        ]);
    }

    protected function authorizeVendorOrder(Request $request, Order $order): void
    {
        $vendorId = $request->user('vendor')?->getAuthIdentifier();

        abort_unless($vendorId && Order::query()
            ->whereKey($order->getKey())
            ->where(function ($query) use ($vendorId) {
                $query->where('vendor_id', $vendorId)
                    ->orWhereHas('details.product', fn ($product) => $product->where('vendor_id', $vendorId));
            })
            ->exists(), 404);
    }
}
