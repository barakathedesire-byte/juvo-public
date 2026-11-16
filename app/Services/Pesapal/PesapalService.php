<?php

namespace App\Services\Pesapal;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Service class for Pesapal v3.0 API integration.
 * Handles authentication, order submission, transaction status, and IPN registration.
 */
class PesapalService
{
    protected ?string $consumerKey;

    protected ?string $consumerSecret;

    protected string $environment = 'sandbox';

    protected string $baseUrl = '';

    protected int $tokenCacheTtl = 240;

    public function __construct()
    {
        $this->consumerKey = config('pesapal.consumer_key');
        $this->consumerSecret = config('pesapal.consumer_secret');
        $this->environment = config('pesapal.environment', 'sandbox');
        $this->baseUrl = config("pesapal.base_urls.{$this->environment}");
        $this->tokenCacheTtl = config('pesapal.token_cache_ttl', 240);
    }

    /**
     * Ensure credentials are available before making API calls.
     */
    protected function ensureCredentials(): void
    {
        if (empty($this->consumerKey) || empty($this->consumerSecret)) {
            throw new RuntimeException('Pesapal credentials are not configured. Set PESAPAL_CONSUMER_KEY and PESAPAL_CONSUMER_SECRET in your .env');
        }
    }

    /**
     * Get the base URL for the current environment.
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Get the current environment.
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }

    /**
     * Authenticate with Pesapal and get an access token.
     * Caches the token for 4 minutes (token expires in 5 minutes).
     */
    public function getAccessToken(): string
    {
        $this->ensureCredentials();
        $cacheKey = "pesapal_access_token_{$this->environment}";

        return Cache::remember($cacheKey, $this->tokenCacheTtl, function () {
            $response = Http::acceptJson()
                ->asJson()
                ->post("{$this->baseUrl}/api/Auth/RequestToken", [
                    'consumer_key' => $this->consumerKey,
                    'consumer_secret' => $this->consumerSecret,
                ]);

            if ($response->failed()) {
                Log::error('Pesapal authentication failed', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);
                throw new RuntimeException('Failed to authenticate with Pesapal: '.$response->body());
            }

            $data = $response->json();

            if (empty($data['token'])) {
                throw new RuntimeException('Pesapal did not return an access token');
            }

            return $data['token'];
        });
    }

    /**
     * Get authorization headers with Bearer token.
     */
    protected function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->getAccessToken(),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Submit an order to Pesapal for payment processing.
     *
     * @param  array  $orderData  Order details including:
     *                            - merchant_reference (string) - Your order reference
     *                            - amount (float) - Order total
     *                            - currency (string) - e.g., 'KES', 'USD', 'UGX', 'TZS'
     *                            - description (string) - Order description
     *                            - callback_url (string) - URL to receive IPN notifications
     *                            - return_url (string) - URL to redirect after payment
     *                            - cancel_url (string) - URL to redirect if cancelled
     *                            - notification_id (string) - IPN registration ID
     *                            - billing_address (array) - Customer billing details
     * @return array Pesapal response with order_tracking_id, redirect_url, etc.
     */
    public function submitOrder(array $orderData): array
    {
        $payload = [
            'id' => $orderData['merchant_reference'],
            'currency' => $orderData['currency'] ?? 'KES',
            'amount' => (float) $orderData['amount'],
            'description' => $orderData['description'] ?? 'Order payment',
            'callback_url' => $orderData['callback_url'],
            'return_url' => $orderData['return_url'] ?? $orderData['callback_url'],
            'cancel_url' => $orderData['cancel_url'] ?? $orderData['callback_url'],
            'notification_id' => $orderData['notification_id'],
            'billing_address' => $orderData['billing_address'] ?? [
                'email_address' => $orderData['email'] ?? '',
                'phone_number' => $orderData['phone'] ?? '',
                'first_name' => $orderData['first_name'] ?? '',
                'last_name' => $orderData['last_name'] ?? '',
                'line_1' => $orderData['address'] ?? '',
                'city' => $orderData['city'] ?? '',
                'state' => $orderData['state'] ?? '',
                'postal_code' => $orderData['postal_code'] ?? '',
                'country_code' => $orderData['country_code'] ?? 'KE',
            ],
        ];

        $response = Http::withHeaders($this->authHeaders())
            ->asJson()
            ->post("{$this->baseUrl}/api/Transactions/SubmitOrderRequest", $payload);

        if ($response->failed()) {
            Log::error('Pesapal SubmitOrderRequest failed', [
                'status' => $response->status(),
                'body' => $response->json(),
                'payload' => $payload,
            ]);
            throw new RuntimeException('Failed to submit order to Pesapal: '.$response->body());
        }

        $data = $response->json();

        if (empty($data['order_tracking_id']) || empty($data['redirect_url'])) {
            throw new RuntimeException('Invalid Pesapal response: missing order_tracking_id or redirect_url');
        }

        return $data;
    }

    /**
     * Get transaction status from Pesapal using order tracking ID.
     *
     * @param  string  $orderTrackingId  The tracking ID returned from SubmitOrderRequest
     * @return array Transaction status details
     */
    public function getTransactionStatus(string $orderTrackingId): array
    {
        $response = Http::withHeaders($this->authHeaders())
            ->get("{$this->baseUrl}/api/Transactions/GetTransactionStatus", [
                'orderTrackingId' => $orderTrackingId,
            ]);

        if ($response->failed()) {
            Log::error('Pesapal GetTransactionStatus failed', [
                'status' => $response->status(),
                'body' => $response->json(),
                'order_tracking_id' => $orderTrackingId,
            ]);
            throw new RuntimeException('Failed to get transaction status: '.$response->body());
        }

        return $response->json();
    }

    /**
     * Register an IPN (Instant Payment Notification) URL with Pesapal.
     *
     * @param  string  $url  The full URL to receive IPN notifications
     * @param  string  $ipnType  'GET' or 'POST'
     * @return array Response containing notification_id
     */
    public function registerIpn(string $url, string $ipnType = 'POST'): array
    {
        $response = Http::withHeaders($this->authHeaders())
            ->asJson()
            ->post("{$this->baseUrl}/api/URLSetup/RegisterIPN", [
                'url' => $url,
                'ipn_notification_type' => $ipnType,
            ]);

        if ($response->failed()) {
            Log::error('Pesapal RegisterIPN failed', [
                'status' => $response->status(),
                'body' => $response->json(),
                'url' => $url,
            ]);
            throw new RuntimeException('Failed to register IPN: '.$response->body());
        }

        return $response->json();
    }

    /**
     * Get list of registered IPN URLs.
     */
    public function getIpnList(): array
    {
        $response = Http::withHeaders($this->authHeaders())
            ->get("{$this->baseUrl}/api/URLSetup/GetIpnList");

        if ($response->failed()) {
            Log::error('Pesapal GetIpnList failed', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
            throw new RuntimeException('Failed to get IPN list: '.$response->body());
        }

        return $response->json();
    }

    /**
     * Verify the webhook signature (Pesapal v3 uses IPN ID validation).
     * Pesapal v3 sends the notification_id in the callback, which we verify
     * by fetching the transaction status.
     */
    public function verifyIpnNotification(string $orderTrackingId, string $notificationId): bool
    {
        try {
            $transaction = $this->getTransactionStatus($orderTrackingId);

            return ($transaction['notification_id'] ?? '') === $notificationId;
        } catch (\Exception $e) {
            Log::warning('IPN verification failed', [
                'order_tracking_id' => $orderTrackingId,
                'notification_id' => $notificationId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
