<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Mobile-money provider catalogue and aggregator hand-off.
 *
 * Juvo only asks the shopper which network they use and the phone number to
 * charge. The aggregator then pushes the PIN prompt to the handset, so the PIN
 * is entered on the phone and never travels through Juvo.
 */
class MobileMoney
{
    /** Gateway code used by the payment_gateways table and on orders. */
    public const GATEWAY_CODE = 'mobilemoney';

    /** Integration key used by the admin Integrations screen. */
    public const AGGREGATOR_INTEGRATION = 'mobile_money_aggregator';

    /**
     * @return array<string, array<string, mixed>>
     */
    public function providers(): array
    {
        return collect(config('mobile_money.providers', []))
            ->filter(fn ($provider) => ($provider['enabled'] ?? true) === true)
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function provider(?string $code): ?array
    {
        if (! $code) {
            return null;
        }

        $code = strtolower(trim($code));

        return $this->providers()[$code] ?? null;
    }

    public function isValidProvider(?string $code): bool
    {
        return $this->provider($code) !== null;
    }

    /**
     * Whether an aggregator account has been configured. Until credentials are
     * present the selector still works, but no charge is initiated.
     */
    public function isConfigured(): bool
    {
        $aggregator = config('mobile_money.aggregator', []);

        return ! empty($aggregator['base_url']) && ! empty($aggregator['api_key']);
    }

    /**
     * Validate a provider + msisdn pair against the catalogue.
     *
     * @return array{provider:string, msisdn:string}
     *
     * @throws ValidationException
     */
    public function validate(?string $provider, ?string $msisdn): array
    {
        $provider = $provider !== null ? strtolower(trim($provider)) : '';
        $msisdn = preg_replace('/\s+/', '', (string) $msisdn);

        $definition = $this->provider($provider);

        if (! $definition) {
            throw ValidationException::withMessages([
                'mobile_money_provider' => 'Please choose a mobile money network.',
            ]);
        }

        $normalised = $this->normaliseMsisdn($msisdn);

        if ($normalised === null) {
            throw ValidationException::withMessages([
                'mobile_money_number' => 'Enter a valid mobile money number (e.g. 0754 123 456).',
            ]);
        }

        $prefixes = $definition['msisdn_prefixes'] ?? [];

        if ($prefixes !== [] && ! in_array(substr($normalised, 1, 2), $prefixes, true)) {
            throw ValidationException::withMessages([
                'mobile_money_number' => 'That number does not look like a '.$definition['label'].' number.',
            ]);
        }

        return ['provider' => $provider, 'msisdn' => $normalised];
    }

    /**
     * Normalise a Tanzanian number to the 0XXXXXXXXX local format used by the
     * aggregators (accepts 07..., 2557..., +2557...).
     */
    public function normaliseMsisdn(?string $msisdn): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $msisdn);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '255') && strlen($digits) === 12) {
            $digits = '0'.substr($digits, 3);
        }

        if (strlen($digits) !== 10 || ! str_starts_with($digits, '0')) {
            return null;
        }

        return $digits;
    }

    /**
     * Hand the charge to the aggregator, which pushes the PIN prompt to the
     * shopper's phone.
     *
     * This is the single integration point. When aggregator credentials are
     * configured it POSTs the collection request; otherwise it throws so callers
     * never silently accept an unpaid order.
     *
     * @return array{status:string, reference:?string, message:string}
     *
     * @throws ValidationException
     */
    public function requestPayment(string $provider, string $msisdn, float $amount, array $meta = []): array
    {
        $definition = $this->provider($provider);

        if (! $definition) {
            throw ValidationException::withMessages([
                'mobile_money_provider' => 'Please choose a mobile money network.',
            ]);
        }

        if (! $this->isConfigured()) {
            throw ValidationException::withMessages([
                'mobile_money_provider' => 'Mobile money is not available right now. Please choose another payment method.',
            ]);
        }

        $aggregator = config('mobile_money.aggregator');

        $payload = array_filter([
            'vendor' => $aggregator['vendor_id'] ?? null,
            'amount' => round($amount, 2),
            'currency' => 'TZS',
            'msisdn' => $msisdn,
            'network' => $definition['aggregator_ref'] ?? $provider,
            'reference' => $meta['reference'] ?? null,
            'callback_url' => $aggregator['callback_url'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$aggregator['api_key'],
                'Accept' => 'application/json',
            ])->timeout(20)->post(rtrim($aggregator['base_url'], '/').'/collections', $payload);
        } catch (\Throwable $e) {
            Log::error('Mobile money request failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'mobile_money_provider' => 'We could not reach the mobile money network. Please try again.',
            ]);
        }

        if ($response->failed()) {
            Log::warning('Mobile money aggregator rejected request', [
                'provider' => $provider,
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            throw ValidationException::withMessages([
                'mobile_money_provider' => 'The mobile money network declined the request. Please try again.',
            ]);
        }

        $body = $response->json() ?? [];

        return [
            'status' => $body['status'] ?? 'pending',
            'reference' => $body['reference'] ?? ($meta['reference'] ?? null),
            'message' => $body['message'] ?? 'Enter your mobile money PIN on your phone to confirm.',
        ];
    }
}
