<?php

namespace App\Services\Payments;

use App\Models\AdCampaign;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Ads\AdPaymentService;
use App\Services\Store\PaymentService;
use Illuminate\Support\Facades\Log;

/**
 * Settles a mobile money collection from the aggregator callback.
 *
 * The aggregator calls back with the reference Juvo sent (the merchant
 * reference) plus the outcome of the collection. The signature is verified
 * before anything is written, and every write is idempotent so a repeated
 * callback never settles a collection twice.
 */
class MobileMoneyCallbackService
{
    /** Payload keys that may carry the reference Juvo sent. */
    protected const REFERENCE_KEYS = [
        'merchant_reference',
        'merchantReference',
        'invoice',
        'order_reference',
        'external_reference',
        'reference',
    ];

    protected const PAID_STATES = ['success', 'successful', 'completed', 'complete', 'paid', 'settled', 'confirmed'];

    protected const FAILED_STATES = ['failed', 'failure', 'rejected', 'declined', 'cancelled', 'canceled', 'expired', 'timeout'];

    public function __construct(
        protected PaymentService $payments,
        protected AdPaymentService $adPayments
    ) {}

    /**
     * @return array{ok:bool, status:string, message:string, order_ids:array<int,int>, campaign_id:?int}
     */
    public function handle(string $rawBody, array $payload, ?string $signature): array
    {
        if (! $this->verifySignature($rawBody, $signature)) {
            Log::warning('Mobile money callback rejected', ['reason' => 'invalid signature']);

            return $this->reject('The aggregator signature could not be verified.');
        }

        $reference = $this->reference($payload);

        if (! $reference) {
            return $this->reject('The aggregator callback did not carry a reference.');
        }

        $orderIds = $this->orderIds($reference);
        $campaign = $this->campaign($reference);

        if ($orderIds === [] && ! $campaign) {
            Log::warning('Mobile money callback could not be matched', ['reference' => $reference]);

            return $this->reject('No order or campaign matches the aggregator reference.');
        }

        $state = $this->state($payload);
        $transactionId = $this->transactionId($payload);

        foreach ($orderIds as $orderId) {
            $order = Order::find($orderId);

            if (! $order) {
                continue;
            }

            if ($state === 'paid') {
                $this->payments->markCompleted($order, $payload, $transactionId);
            } elseif ($state === 'failed') {
                $this->payments->markFailed($order, $payload, $transactionId);
            }
        }

        if ($campaign) {
            if ($state === 'paid') {
                $this->adPayments->settle($campaign, 'Payment verified by the mobile money aggregator.');
            } elseif ($state === 'failed') {
                $campaign->update([
                    'status' => 'failed',
                    'admin_note' => 'The mobile money collection was not completed.',
                ]);
            }
        }

        return [
            'ok' => true,
            'status' => $state,
            'message' => 'The mobile money collection was recorded.',
            'order_ids' => $orderIds,
            'campaign_id' => $campaign?->id,
        ];
    }

    /**
     * Verify the aggregator signature (HMAC-SHA256 over the raw body).
     */
    public function verifySignature(string $rawBody, ?string $signature): bool
    {
        $secret = (string) (config('mobile_money.aggregator.callback_secret')
            ?: config('mobile_money.aggregator.api_secret'));

        if ($secret === '') {
            Log::warning('Mobile money callback rejected', ['reason' => 'no callback secret configured']);

            return false;
        }

        if (! $signature) {
            return false;
        }

        $provided = trim($signature);

        if (str_starts_with(strtolower($provided), 'sha256=')) {
            $provided = substr($provided, 7);
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);

        if (hash_equals($expected, $provided)) {
            return true;
        }

        return hash_equals(base64_encode($expected), $provided);
    }

    /**
     * The reference Juvo sent with the collection request.
     */
    protected function reference(array $payload): ?string
    {
        foreach (self::REFERENCE_KEYS as $key) {
            $value = $payload[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * Normalise the aggregator status into paid / failed / pending.
     */
    protected function state(array $payload): string
    {
        $status = strtolower(trim((string) ($payload['status'] ?? $payload['state'] ?? '')));

        if (in_array($status, self::PAID_STATES, true)) {
            return 'paid';
        }

        if (in_array($status, self::FAILED_STATES, true)) {
            return 'failed';
        }

        return 'pending';
    }

    protected function transactionId(array $payload): ?string
    {
        foreach (['transaction_id', 'transactionId', 'checkout_id', 'collection_id', 'reference'] as $key) {
            $value = $payload[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * Every order covered by the collection. A checkout can span several
     * vendors, so the order ids are stored with the payment.
     *
     * @return array<int,int>
     */
    protected function orderIds(string $reference): array
    {
        $payment = Payment::where('payment_method', MobileMoney::GATEWAY_CODE)
            ->where(function ($query) use ($reference) {
                $query->where('meta->mobile_money->merchant_reference', $reference)
                    ->orWhere('meta->mobile_money->reference', $reference);
            })
            ->latest('id')
            ->first();

        if ($payment) {
            $meta = $payment->meta ?? [];
            $ids = $meta['mobile_money']['order_ids'] ?? [];

            if (is_array($ids) && $ids !== []) {
                return array_values(array_unique(array_map('intval', $ids)));
            }

            return [$payment->order_id];
        }

        if (preg_match('/^ORDER-(\d+)$/i', $reference, $matches)) {
            return [(int) $matches[1]];
        }

        return [];
    }

    protected function campaign(string $reference): ?AdCampaign
    {
        $campaign = AdCampaign::where('payment_method', MobileMoney::GATEWAY_CODE)
            ->where(function ($query) use ($reference) {
                $query->where('payment_meta->merchant_reference', $reference)
                    ->orWhere('payment_meta->reference', $reference);
            })
            ->latest('id')
            ->first();

        if ($campaign) {
            return $campaign;
        }

        if (preg_match('/^AD-(\d+)$/i', $reference, $matches)) {
            return AdCampaign::find((int) $matches[1]);
        }

        return null;
    }

    /**
     * @return array{ok:bool, status:string, message:string, order_ids:array<int,int>, campaign_id:?int}
     */
    protected function reject(string $message): array
    {
        return [
            'ok' => false,
            'status' => 'rejected',
            'message' => $message,
            'order_ids' => [],
            'campaign_id' => null,
        ];
    }
}
