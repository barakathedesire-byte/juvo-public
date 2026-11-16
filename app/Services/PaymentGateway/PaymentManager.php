<?php

namespace App\Services\PaymentGateway;

use App\Models\PaymentGateway;
use InvalidArgumentException;

class PaymentManager
{
    protected const SERVICES = [
        'paypal' => PayPalService::class,
        'stripe' => StripeService::class,
    ];

    public static function supports(string $gatewayCode): bool
    {
        return array_key_exists($gatewayCode, self::SERVICES);
    }

    /**
     * Create a payment service instance for the given gateway code. When no
     * environment is supplied the gateway's configured environment wins, so an
     * admin who saved live credentials gets live traffic automatically.
     *
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public static function make(string $gatewayCode, ?string $environment = null)
    {
        $gateway = PaymentGateway::where('code', $gatewayCode)
            ->where('is_active', true)
            ->first();

        if (! $gateway) {
            throw new InvalidArgumentException("Payment gateway [{$gatewayCode}] not found or inactive.");
        }

        if (! self::supports($gatewayCode)) {
            throw new InvalidArgumentException("No service class found for [{$gatewayCode}].");
        }

        $serviceClass = self::SERVICES[$gatewayCode];

        return new $serviceClass($environment ?: $gateway->preferredEnvironment());
    }
}
