<?php

namespace App\Services\Intelligence;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\RecommendationEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Ingests the recommendation/behaviour event stream. All inputs are treated
 * as untrusted: types are whitelisted, purchases are validated against real
 * orders, values are re-derived server-side and duplicate impressions are
 * suppressed.
 */
class RecommendationEventService
{
    public function record(array $data): RecommendationEvent
    {
        $type = strtolower((string) ($data['type'] ?? ''));
        if (! in_array($type, RecommendationEvent::TYPES, true)) {
            throw new \InvalidArgumentException("Unsupported event type [{$type}].");
        }

        $uuid = $data['event_uuid'] ?? (string) Str::uuid();

        if ($existing = RecommendationEvent::where('event_uuid', $uuid)->first()) {
            return $existing;
        }

        $surface = (string) ($data['surface'] ?? 'unknown');
        $productId = isset($data['product_id']) ? (int) $data['product_id'] : null;
        $sessionId = $data['session_id'] ?? null;

        $isValid = true;
        $invalidReason = null;
        $valueMinor = (int) ($data['value_minor'] ?? 0);
        $orderId = isset($data['order_id']) ? (int) $data['order_id'] : null;
        $order = null;

        if ($type === 'purchase') {
            [$isValid, $invalidReason, $order] = $this->validatePurchase($data);
            if ($isValid && $order) {
                // Never trust client totals: derive from the real order.
                $valueMinor = (int) round(((float) $order->total_amount) * 100);
                $productId = $productId ?: $order->details()->value('product_id');
            }
        } elseif ($this->isDuplicateImpression($type, $surface, $productId, $sessionId)) {
            $isValid = false;
            $invalidReason = 'duplicate';
        }

        $meta = $data['meta'] ?? [];
        if ($productId && (empty($data['category_id']) || empty($data['vendor_id']))) {
            $meta = array_merge((array) $meta, $this->productMeta($productId));
        }

        return RecommendationEvent::create([
            'event_uuid' => $uuid,
            'type' => $type,
            'algorithm_key' => $data['algorithm_key'] ?? RecommendationService::ALGORITHM_KEY,
            'algorithm_version' => $data['algorithm_version'] ?? null,
            'surface' => $surface,
            'subject_type' => $data['subject_type'] ?? null,
            'subject_id' => $data['subject_id'] ?? null,
            'product_id' => $productId,
            'category_id' => $data['category_id'] ?? ($meta['category_id'] ?? null),
            'vendor_id' => $data['vendor_id'] ?? ($meta['vendor_id'] ?? null),
            'order_id' => $orderId,
            'value_minor' => $valueMinor,
            'currency' => $data['currency'] ?? ($order->currency ?? null),
            'position' => $data['position'] ?? null,
            'session_id' => $sessionId,
            'is_valid' => $isValid,
            'invalid_reason' => $invalidReason,
            'meta' => $meta ?: null,
            'occurred_at' => isset($data['occurred_at']) ? Carbon::parse($data['occurred_at']) : now(),
        ]);
    }

    protected function isDuplicateImpression(?string $type, string $surface, ?int $productId, ?string $sessionId): bool
    {
        if ($type !== 'impression' || ! $productId || ! $sessionId) {
            return false;
        }

        return RecommendationEvent::where('type', 'impression')
            ->where('surface', $surface)
            ->where('product_id', $productId)
            ->where('session_id', $sessionId)
            ->where('occurred_at', '>=', now()->subSeconds(90))
            ->exists();
    }

    /**
     * @return array{0:bool,1:?string,2:?Order}
     */
    protected function validatePurchase(array $data): array
    {
        $orderId = $data['order_id'] ?? null;
        if (! $orderId) {
            return [false, 'missing_order', null];
        }

        $order = Order::find($orderId);
        if (! $order) {
            return [false, 'unknown_order', null];
        }

        $subjectId = $data['subject_id'] ?? null;
        if ($subjectId && ($data['subject_type'] ?? null) === Customer::class && (int) $order->customer_id !== (int) $subjectId) {
            return [false, 'subject_mismatch', null];
        }

        return [true, null, $order];
    }

    protected function productMeta(?int $productId): array
    {
        if (! $productId) {
            return [];
        }

        $product = Product::find($productId, ['id', 'category_id', 'vendor_id']);

        return $product ? [
            'category_id' => $product->category_id,
            'vendor_id' => $product->vendor_id,
        ] : [];
    }
}
