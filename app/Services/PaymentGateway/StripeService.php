<?php

namespace App\Services\PaymentGateway;

use App\Models\PaymentGateway;
use Stripe\StripeClient;

class StripeService
{
    protected $environment;

    protected $client;

    protected $publicKey;

    public function __construct($environment = 'sandbox')
    {
        $this->environment = $environment;

        $gateway = PaymentGateway::where('code', 'stripe')->first();

        $secretKey = $gateway
            ? $gateway->getConfigValueAny(['secret', 'secret_key', 'private_key'], null, $environment)
            : null;

        $this->publicKey = $gateway
            ? $gateway->getConfigValueAny(['public', 'public_key', 'publishable_key'], null, $environment)
            : null;

        if (! $secretKey) {
            throw new \Exception('Stripe secret key not found! Add it in Admin → Payment Gateways.');
        }

        $this->client = new StripeClient($secretKey);
    }

    public function getPublicKey()
    {
        return $this->publicKey;
    }

    public function createPaymentIntent($amount, $currency = 'usd')
    {
        return $this->client->paymentIntents->create([
            'amount' => $this->minorUnits($amount, $currency),
            'currency' => strtolower($currency),
        ]);
    }

    public function createCheckoutSession(float $amount, string $currency, string $successUrl, string $cancelUrl, array $metadata = [])
    {
        return $this->client->checkout->sessions->create([
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($currency),
                    'unit_amount' => $this->minorUnits($amount, $currency),
                    'product_data' => ['name' => 'Juvo order'],
                ],
            ]],
            'metadata' => $metadata,
        ]);
    }

    public function retrieveCheckoutSession(string $sessionId)
    {
        return $this->client->checkout->sessions->retrieve($sessionId);
    }

    /**
     * Stripe expects amounts in the smallest currency unit. Most currencies
     * have two decimals, but zero-decimal currencies must not be multiplied.
     */
    protected function minorUnits(float $amount, string $currency): int
    {
        $zeroDecimal = ['bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf'];

        if (in_array(strtolower($currency), $zeroDecimal, true)) {
            return (int) round($amount);
        }

        return (int) round($amount * 100);
    }
}
