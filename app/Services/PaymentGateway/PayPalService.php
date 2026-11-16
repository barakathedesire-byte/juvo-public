<?php

namespace App\Services\PaymentGateway;

use App\Models\PaymentGateway;
use App\Models\PaymentGatewayConfig;
use Illuminate\Support\Facades\Http;

class PayPalService
{
    protected $clientId;

    protected $secret;

    protected $mode;

    protected $baseUrl;

    public function __construct($environment = 'sandbox')
    {
        $gateway = PaymentGateway::where('code', 'paypal')
            ->where('is_active', true)
            ->firstOrFail();

        $configs = PaymentGatewayConfig::where('gateway_id', $gateway->id)
            ->where('environment', $environment)
            ->get()
            ->pluck('key_value', 'key_name');

        $this->clientId = $configs['client_id'] ?? $configs['client_key'] ?? null;
        $this->secret = $configs['secret'] ?? $configs['client_secret'] ?? $configs['secret_key'] ?? null;
        $this->mode = $environment;

        $this->baseUrl = $this->mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private function getAccessToken()
    {
        if (! $this->clientId || ! $this->secret) {
            throw new \RuntimeException('PayPal is not configured. Add a client ID and secret in Admin → Payment Gateways.');
        }

        $response = Http::withBasicAuth($this->clientId, $this->secret)
            ->asForm()
            ->post("{$this->baseUrl}/v1/oauth2/token", [
                'grant_type' => 'client_credentials',
            ]);

        $token = $response->json()['access_token'] ?? null;

        if (! $token) {
            throw new \RuntimeException('PayPal authentication failed. Check the configured credentials.');
        }

        return $token;
    }

    public function createOrder($amount, $currency = 'USD')
    {
        $accessToken = $this->getAccessToken();

        $response = Http::withToken($accessToken)->post("{$this->baseUrl}/v2/checkout/orders", [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => $amount,
                    ],
                ],
            ],
            'application_context' => [
                'cancel_url' => route('paypal.cancel'),
                'return_url' => route('paypal.success'),
            ],
        ]);

        $payload = $response->json();

        if (empty($payload['id'])) {
            throw new \RuntimeException($payload['message'] ?? 'PayPal could not create the order.');
        }

        return $payload;
    }

    public function approvalUrl(array $order): ?string
    {
        foreach (($order['links'] ?? []) as $link) {
            if (($link['rel'] ?? null) === 'approve') {
                return $link['href'] ?? null;
            }
        }

        return null;
    }

    public function captureOrder($orderId)
    {
        $accessToken = $this->getAccessToken();

        $response = Http::withToken($accessToken)
            ->post("{$this->baseUrl}/v2/checkout/orders/{$orderId}/capture");

        return $response->json();
    }
}
