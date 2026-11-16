<?php

namespace App\Services\Store;

use App\Models\PaymentGateway;
use Illuminate\Validation\ValidationException;

/**
 * Resolves and validates the shopper's chosen payment method (Instant Pay vs
 * Pay on Delivery) entirely server-side. The client only expresses intent;
 * the authoritative gateway must be an active, configured instant gateway or
 * the recognised pay-on-delivery code. Never trusts a bare gateway string.
 */
class PaymentMethodResolver
{
    public const CATEGORY_INSTANT = 'instant_pay';

    public const CATEGORY_POD = 'pay_on_delivery';

    /** Canonical stored code for pay-on-delivery. */
    public const POD_CODE = 'payondelivery';

    protected const POD_ALIASES = ['cod', 'payondelivery', 'pod', 'pay_on_delivery'];

    public function isPayOnDelivery(?string $code): bool
    {
        return $code === null || $code === '' || in_array(strtolower($code), self::POD_ALIASES, true);
    }

    public function isInstantCategory(?string $category): bool
    {
        return $category !== null && strtolower($category) === self::CATEGORY_INSTANT;
    }

    public function activeGateway(?string $code): ?PaymentGateway
    {
        if (! $code) {
            return null;
        }

        return PaymentGateway::with('configs')->where('code', $code)->where('is_active', 1)->first();
    }

    /**
     * @return array{category:string, method:string, gateway:?PaymentGateway}
     *
     * @throws ValidationException when the requested method is not available.
     */
    public function resolve(?string $category, ?string $gateway): array
    {
        $category = $category !== null ? strtolower($category) : null;
        $gateway = $gateway !== null ? strtolower($gateway) : null;

        $wantsInstant = $this->isInstantCategory($category)
            || ($category === null && ! $this->isPayOnDelivery($gateway));

        if ($wantsInstant) {
            $model = $this->activeGateway($gateway);

            if (! $model) {
                throw ValidationException::withMessages([
                    'gateway' => 'Please choose an available instant payment method.',
                ]);
            }

            return [
                'category' => self::CATEGORY_INSTANT,
                'method' => $model->code,
                'gateway' => $model,
            ];
        }

        return [
            'category' => self::CATEGORY_POD,
            'method' => self::POD_CODE,
            'gateway' => null,
        ];
    }
}
