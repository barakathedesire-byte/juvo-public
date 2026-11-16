<?php

namespace App\Services\Logistics;

use App\Models\Order;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Service for integrating with Juvo Logistics platform.
 * Handles order fulfillment, driver dispatch, tracking, and webhook processing.
 */
class JuvoLogisticsService
{
    protected string $baseUrl;

    protected string $apiKey;

    protected string $apiSecret;

    protected int $timeout;

    protected int $retryAttempts;

    protected int $retryDelay;

    protected bool $enabled;

    public function __construct()
    {
        $this->enabled = config('juvo-logistics.enabled');
        $this->baseUrl = rtrim(config('juvo-logistics.api.base_url'), '/');
        $this->apiKey = config('juvo-logistics.api.api_key');
        $this->apiSecret = config('juvo-logistics.api.api_secret');
        $this->timeout = config('juvo-logistics.api.timeout');
        $this->retryAttempts = config('juvo-logistics.api.retry_attempts');
        $this->retryDelay = config('juvo-logistics.api.retry_delay');
    }

    /**
     * Check if the integration is enabled and configured.
     */
    public function isEnabled(): bool
    {
        return $this->enabled
            && ! empty($this->baseUrl)
            && ! empty($this->apiKey)
            && ! empty($this->apiSecret);
    }

    /**
     * Get the authenticated HTTP client.
     */
    protected function client(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::timeout($this->timeout)
            ->retry($this->retryAttempts, $this->retryDelay)
            ->withHeaders([
                'Authorization' => 'Bearer '.$this->getAccessToken(),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-Request-ID' => (string) Str::uuid(),
            ]);
    }

    /**
     * Get or generate access token.
     * In production, this should use OAuth2 client credentials flow.
     */
    protected function getAccessToken(): string
    {
        // For now, use API key directly. In production, implement OAuth2 token exchange.
        return $this->apiKey.':'.$this->apiSecret;
    }

    // =========================================================================
    // ORDER FULFILLMENT
    // =========================================================================

    /**
     * Create a delivery order in Juvo Logistics.
     */
    public function createDeliveryOrder(Order $order): array
    {
        if (! $this->isEnabled()) {
            throw new RuntimeException('Juvo Logistics integration is not enabled or configured');
        }

        $payload = $this->buildDeliveryOrderPayload($order);

        $response = $this->client()->post("{$this->baseUrl}/orders", $payload);

        if ($response->failed()) {
            $this->handleApiError('createDeliveryOrder', $order->id, $response);
            throw new RuntimeException('Failed to create delivery order: '.$response->body());
        }

        $body = $response->json();
        $data = is_array($body['data'] ?? null) ? $body['data'] : $body;

        if (! is_array($data) || empty($data['id'])) {
            throw new RuntimeException('Juvo Logistics returned no tracking ID for the delivery order');
        }

        // Update order with Juvo Logistics tracking info
        $order->update([
            'logistics_tracking_id' => $data['id'] ?? null,
            'logistics_carrier' => $data['carrier'] ?? config('juvo-logistics.fulfillment.default_carrier'),
            'logistics_status' => $this->normalizeLogisticsStatus($data['status'] ?? 'pending'),
            'logistics_data' => $data,
        ]);

        Log::info('Juvo Logistics delivery order created', [
            'order_id' => $order->id,
            'tracking_id' => $data['id'] ?? null,
        ]);

        return $data;
    }

    /**
     * Build the payload for creating a delivery order.
     */
    protected function buildDeliveryOrderPayload(Order $order): array
    {
        $delivery = $order->shippingAddress;
        $customer = $order->customer;
        $details = $order->details;
        $orderNumber = $order->order_number ?: 'JUVO-'.$order->id;
        $customerEmail = $customer?->email ?: $order->guest_email;

        if (! filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            $customerEmail = null;
        }

        $lineItems = $details->map(static function ($detail): array {
            return array_filter([
                'productId' => $detail->product_id ? (string) $detail->product_id : null,
                'variantId' => $detail->variant_id ? (string) $detail->variant_id : null,
                'quantity' => max(1, (int) $detail->quantity),
                'unitPrice' => (float) ($detail->price ?? 0),
            ], static fn ($value) => $value !== null);
        })->values()->all();

        return array_filter([
            'shopifyOrderId' => (string) $orderNumber,
            'shopifyOrderNumber' => $order->order_number ? (string) $order->order_number : null,
            'source' => 'JUVO_MARKETPLACE',
            'customerName' => $delivery?->name ?: $customer?->name,
            'customerEmail' => $customerEmail,
            'customerPhone' => $delivery?->phone ?: $customer?->phone,
            'addressLine1' => $delivery?->address,
            'city' => $delivery?->city,
            'province' => $delivery?->state,
            'postalCode' => $delivery?->postal_code,
            'country' => $delivery?->country ?: 'Tanzania',
            'totalPrice' => (float) ($order->total_amount ?? 0),
            'currency' => (string) ($order->currency ?: 'TZS'),
            'itemCount' => max(1, (int) $details->sum('quantity')),
            'lineItems' => $lineItems,
            'fulfillmentType' => (string) ($order->fulfillment_type ?: 'delivery'),
            'paymentMethod' => $order->payment_method ?: null,
            'paymentStatus' => $order->payment_status ?: null,
            'notes' => $order->note ?: null,
            'tags' => array_values(array_filter([
                'marketplace',
                $order->fulfillment_type,
            ])),
        ], static fn ($value) => $value !== null && $value !== '');
    }

    /**
     * Cancel a delivery order.
     */
    public function cancelDeliveryOrder(Order $order, string $reason = 'Cancelled by merchant'): bool
    {
        if (! $order->logistics_tracking_id) {
            return false;
        }

        $response = $this->client()->delete(
            "{$this->baseUrl}/orders/{$order->logistics_tracking_id}"
        );

        if ($response->successful()) {
            $order->update(['logistics_status' => 'cancelled']);

            return true;
        }

        Log::warning('Failed to cancel Juvo Logistics order', [
            'order_id' => $order->id,
            'tracking_id' => $order->logistics_tracking_id,
            'response' => $response->body(),
        ]);

        return false;
    }

    /**
     * Get delivery order status.
     */
    public function getDeliveryStatus(Order $order): ?array
    {
        if (! $order->logistics_tracking_id) {
            return null;
        }

        $response = $this->client()->get("{$this->baseUrl}/orders/{$order->logistics_tracking_id}");

        if ($response->failed()) {
            return null;
        }

        $body = $response->json();

        return is_array($body['data'] ?? null) ? $body['data'] : $body;
    }

    // =========================================================================
    // DRIVER MANAGEMENT
    // =========================================================================

    /**
     * Get available drivers near a location.
     */
    public function getAvailableDrivers(float $lat, float $lng, int $radiusKm = 10): array
    {
        $response = $this->client()->get("{$this->baseUrl}/drivers/available", [
            'lat' => $lat,
            'lng' => $lng,
            'radius_km' => $radiusKm,
        ]);

        if ($response->failed()) {
            return [];
        }

        return $response->json()['drivers'] ?? [];
    }

    /**
     * Assign driver to order.
     */
    public function assignDriver(string $trackingId, string $driverId): bool
    {
        $response = $this->client()->patch("{$this->baseUrl}/orders/{$trackingId}/assign", [
            'driverId' => $driverId,
        ]);

        return $response->successful();
    }

    // =========================================================================
    // TRACKING
    // =========================================================================

    /**
     * Get real-time tracking information.
     */
    public function getTracking(string $trackingId): ?array
    {
        $response = $this->client()->get("{$this->baseUrl}/tracking/{$trackingId}");

        if ($response->failed()) {
            return null;
        }

        $body = $response->json();

        return is_array($body['data'] ?? null) ? $body['data'] : $body;
    }

    /**
     * Get tracking information by public tracking token.
     */
    public function getTrackingByToken(string $trackingToken): ?array
    {
        try {
            $response = $this->client()->get("{$this->baseUrl}/tracking/token/{$trackingToken}");
        } catch (RequestException) {
            return null;
        }

        if ($response->failed()) {
            return null;
        }

        $body = $response->json();

        return is_array($body['data'] ?? null) ? $body['data'] : null;
    }

    /**
     * Get tracking URL for customer.
     */
    public function getTrackingUrl(string $trackingId, ?string $trackingToken = null): string
    {
        $baseUrl = config('juvo-logistics.tracking.public_tracking_url');

        return $trackingToken ? "{$baseUrl}/track/{$trackingToken}" : $baseUrl;
    }

    /**
     * Get embedded tracking iframe URL.
     */
    public function getEmbedTrackingUrl(string $trackingId, ?string $trackingToken = null): string
    {
        $baseUrl = config('juvo-logistics.tracking.public_tracking_url');

        return $trackingToken ? "{$baseUrl}/embed/track/{$trackingToken}" : $baseUrl;
    }

    // =========================================================================
    // WEBHOOK PROCESSING
    // =========================================================================

    /**
     * Verify webhook signature.
     */
    public function verifyWebhook(string $payload, string $signature): bool
    {
        $secret = config('juvo-logistics.webhook.secret');

        if (empty($secret)) {
            Log::error('Juvo Logistics webhook rejected: secret not configured');

            return false;
        }

        $signature = trim($signature);
        if (str_starts_with(strtolower($signature), 'sha256=')) {
            $signature = substr($signature, 7);
        }

        $expectedHex = hash_hmac('sha256', $payload, $secret);
        $expectedBase64 = base64_encode(hash_hmac('sha256', $payload, $secret, true));

        return hash_equals($expectedHex, $signature) || hash_equals($expectedBase64, $signature);
    }

    /**
     * Process incoming webhook.
     */
    public function processWebhook(array $payload): array
    {
        $payload = $this->normalizeWebhookPayload($payload);
        $eventType = $this->normalizeEventType($payload);

        Log::info('Juvo Logistics webhook received', [
            'event_type' => $eventType,
            'tracking_id' => $this->webhookTrackingId($payload),
        ]);

        return match ($eventType) {
            'order.created' => $this->handleOrderCreated($payload),
            'order.assigned' => $this->handleOrderAssigned($payload),
            'order.picked_up' => $this->handleOrderPickedUp($payload),
            'order.in_transit' => $this->handleOrderInTransit($payload),
            'order.delivered' => $this->handleOrderDelivered($payload),
            'order.failed' => $this->handleOrderFailed($payload),
            'order.cancelled' => $this->handleOrderCancelled($payload),
            'driver.location_updated' => $this->handleDriverLocationUpdate($payload),
            'proof_of_delivery.uploaded' => $this->handleProofOfDelivery($payload),
            default => ['processed' => false, 'reason' => 'Unknown event type: '.$eventType],
        };
    }

    // Webhook handlers
    protected function handleOrderCreated(array $payload): array
    {
        $order = $this->webhookOrder($payload);
        if ($order) {
            $order->update(['logistics_status' => 'created']);
        }

        return ['processed' => true, 'action' => 'order_created'];
    }

    protected function handleOrderAssigned(array $payload): array
    {
        $order = $this->webhookOrder($payload);
        if ($order) {
            $data = $order->logistics_data ?? [];
            $data['driverId'] = $payload['driver_id'] ?? $payload['driverId'] ?? null;
            $order->update([
                'logistics_status' => 'assigned',
                'logistics_data' => $data,
                'driver_name' => $payload['driver_name'] ?? $payload['driverName'] ?? null,
                'driver_phone' => $payload['driver_phone'] ?? $payload['driverPhone'] ?? null,
            ]);
        }

        return ['processed' => true, 'action' => 'order_assigned'];
    }

    protected function handleOrderPickedUp(array $payload): array
    {
        $order = $this->webhookOrder($payload);
        if ($order) {
            $order->update([
                'logistics_status' => 'picked_up',
                'picked_up_at' => now(),
            ]);
            $order->statusHistories()->create([
                'status' => 'picked_up',
                'comment' => 'Order picked up by driver: '.($payload['driver_name'] ?? $payload['driverName'] ?? 'Unknown'),
                'changed_by' => 'logistics',
            ]);
        }

        return ['processed' => true, 'action' => 'picked_up'];
    }

    protected function handleOrderInTransit(array $payload): array
    {
        $order = $this->webhookOrder($payload);
        if ($order) {
            $order->update([
                'logistics_status' => 'in_transit',
                'driver_lat' => $payload['lat'] ?? $payload['latitude'] ?? null,
                'driver_lng' => $payload['lng'] ?? $payload['longitude'] ?? null,
            ]);
        }

        return ['processed' => true, 'action' => 'in_transit'];
    }

    protected function handleOrderDelivered(array $payload): array
    {
        $order = $this->webhookOrder($payload);
        if ($order) {
            $paymentCollected = ($payload['payment_status'] ?? $payload['paymentStatus'] ?? null) === 'paid'
                || ($payload['cod_collected'] ?? $payload['codCollected'] ?? false);
            $order->update([
                'logistics_status' => 'delivered',
                'delivered_at' => now(),
                'status' => 'delivered',
                'payment_status' => $paymentCollected ? 'paid' : $order->payment_status,
            ]);
            $order->statusHistories()->create([
                'status' => 'delivered',
                'comment' => 'Order delivered successfully',
                'changed_by' => 'logistics',
            ]);
        }

        return ['processed' => true, 'action' => 'delivered'];
    }

    protected function handleOrderFailed(array $payload): array
    {
        $order = $this->webhookOrder($payload);
        if ($order) {
            $order->update(['logistics_status' => 'failed']);
            $order->statusHistories()->create([
                'status' => 'delivery_failed',
                'comment' => 'Delivery failed: '.($payload['reason'] ?? 'Unknown reason'),
                'changed_by' => 'logistics',
            ]);
        }

        return ['processed' => true, 'action' => 'failed'];
    }

    protected function handleOrderCancelled(array $payload): array
    {
        $order = $this->webhookOrder($payload);
        if ($order) {
            $order->update([
                'logistics_status' => 'cancelled',
                'status' => 'canceled',
                'canceled_at' => now(),
                'cancel_reason' => $payload['reason'] ?? $payload['cancelReason'] ?? 'Cancelled by logistics provider',
            ]);
        }

        return ['processed' => true, 'action' => 'cancelled'];
    }

    protected function handleDriverLocationUpdate(array $payload): array
    {
        $order = $this->webhookOrder($payload);
        if ($order) {
            $order->update([
                'driver_lat' => $payload['lat'] ?? $payload['latitude'] ?? null,
                'driver_lng' => $payload['lng'] ?? $payload['longitude'] ?? null,
            ]);
        }

        return ['processed' => true, 'action' => 'location_updated'];
    }

    protected function handleProofOfDelivery(array $payload): array
    {
        $order = $this->webhookOrder($payload);
        if ($order) {
            $order->update([
                'pod_photo_url' => $payload['photo_url'] ?? $payload['photoUrl'] ?? null,
                'pod_signature_url' => $payload['signature_url'] ?? $payload['signatureUrl'] ?? null,
                'pod_otp_verified' => $payload['otp_verified'] ?? $payload['otpVerified'] ?? false,
                'pod_delivered_at' => now(),
            ]);
        }

        return ['processed' => true, 'action' => 'pod_uploaded'];
    }

    protected function normalizeWebhookPayload(array $payload): array
    {
        $nested = $payload['data'] ?? $payload['payload'] ?? [];

        return is_array($nested) ? array_merge($payload, $nested) : $payload;
    }

    protected function normalizeEventType(array $payload): string
    {
        $eventType = strtolower((string) ($payload['event_type'] ?? $payload['eventType'] ?? $payload['type'] ?? 'unknown'));
        $eventType = str_replace('-', '_', $eventType);

        if (in_array($eventType, ['order.status_changed', 'delivery.status_changed'], true)) {
            return match (strtolower((string) ($payload['new_status'] ?? $payload['newStatus'] ?? $payload['status'] ?? 'unknown'))) {
                'created', 'pending', 'accepted', 'confirmed' => 'order.created',
                'assigned' => 'order.assigned',
                'picked_up', 'pickedup' => 'order.picked_up',
                'out_for_delivery', 'in_transit', 'arrived' => 'order.in_transit',
                'delivered' => 'order.delivered',
                'failed' => 'order.failed',
                'returned', 'cancelled', 'canceled' => 'order.cancelled',
                default => 'unknown',
            };
        }

        return $eventType;
    }

    protected function webhookTrackingId(array $payload): ?string
    {
        foreach (['tracking_id', 'trackingId', 'order_id', 'orderId', 'delivery_id', 'deliveryId'] as $key) {
            if (isset($payload[$key]) && is_scalar($payload[$key])) {
                return (string) $payload[$key];
            }
        }

        return null;
    }

    protected function webhookOrder(array $payload): ?Order
    {
        $trackingId = $this->webhookTrackingId($payload);

        return $trackingId
            ? Order::where('logistics_tracking_id', $trackingId)->first()
            : null;
    }

    protected function normalizeLogisticsStatus(?string $status): string
    {
        return match (strtolower((string) $status)) {
            'out_for_delivery' => 'in_transit',
            'canceled' => 'cancelled',
            default => strtolower((string) ($status ?: 'pending')),
        };
    }

    // =========================================================================
    // ERROR HANDLING
    // =========================================================================

    protected function handleApiError(string $operation, int $orderId, Response $response): void
    {
        Log::error("Juvo Logistics API error: {$operation}", [
            'order_id' => $orderId,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);
    }

    // =========================================================================
    // UTILITY METHODS
    // =========================================================================

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Health check for the logistics API.
     */
    public function healthCheck(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/health");

            return $response->successful() && ($response->json()['status'] ?? '') === 'ok';
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Sync order statuses in bulk.
     */
    public function syncOrderStatuses(array $orderIds): array
    {
        $results = [];
        foreach ($orderIds as $orderId) {
            $order = Order::find($orderId);
            if ($order && $order->logistics_tracking_id) {
                $status = $this->getDeliveryStatus($order);
                if ($status) {
                    $results[$orderId] = $status;
                }
            }
        }

        return $results;
    }
}
