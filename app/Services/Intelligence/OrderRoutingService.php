<?php

namespace App\Services\Intelligence;

use App\Models\Order;
use App\Models\ShippingRate;
use App\Models\Vendor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Algorithm 09 — Order Routing & Fulfilment.
 *
 * Scores every fulfilment path actually available for an order — vendor
 * self-fulfilment, the delivery partner, or flat-rate shipping — on cost,
 * reliability and coverage, then returns them ranked with the reason each one
 * placed where it did. A path that is unavailable is returned as unavailable
 * rather than silently omitted, so an operator can see what is missing.
 */
class OrderRoutingService
{
    public const ALGORITHM_KEY = 'order_routing';

    public function __construct(protected AlgorithmRegistry $registry) {}

    public function isEnabled(): bool
    {
        return $this->registry->isActive(self::ALGORITHM_KEY);
    }

    public function config(): array
    {
        return $this->registry->configFor(self::ALGORITHM_KEY);
    }

    /**
     * @return array{
     *     order_id: int,
     *     recommended: string|null,
     *     paths: array<int, array{name: string, available: bool, score: float|null, reasons: array<int, string>}>
     * }
     */
    public function recommend(Order $order): array
    {
        $config = $this->config();

        $weights = array_merge([
            'cost' => 0.35,
            'reliability' => 0.40,
            'coverage' => 0.25,
        ], (array) ($config['weights'] ?? []));

        $vendor = $order->vendor_id ? Vendor::find($order->vendor_id) : null;
        $fee = (float) $order->delivery_fee;
        $total = (float) ($order->subtotal ?: $order->total_amount);

        $paths = [
            $this->vendorDirect($vendor, $weights, $fee),
            $this->deliveryPartner($weights, $fee),
            $this->flatRate($total, $weights, $fee),
        ];

        $available = collect($paths)
            ->filter(fn (array $path) => $path['available'])
            ->sortByDesc('score')
            ->values();

        return [
            'order_id' => $order->id,
            'recommended' => $available->first()['name'] ?? null,
            'paths' => $paths,
        ];
    }

    protected function vendorDirect(?Vendor $vendor, array $weights, float $fee): array
    {
        $reasons = [];

        $hasLocation = $vendor && $vendor->latitude !== null && $vendor->longitude !== null;
        $hasCoverage = $vendor && ! empty((array) ($vendor->delivery_coverage ?? []));
        $available = (bool) ($hasLocation || $hasCoverage);

        if (! $available) {
            $reasons[] = $vendor
                ? 'Vendor has no pickup location or delivery coverage configured.'
                : 'Order has no vendor attached.';
        } else {
            $reasons[] = 'Vendor can fulfil directly.';
        }

        $reliability = $vendor ? $this->vendorFulfilmentRate((int) $vendor->id) : 0.0;
        $coverage = $hasLocation ? 1.0 : ($hasCoverage ? 0.6 : 0.0);

        return [
            'name' => 'vendor_direct',
            'available' => $available,
            'score' => $available ? round($this->combine($this->costScore($fee), $reliability, $coverage, $weights), 2) : null,
            'reasons' => $reasons,
            'components' => ['cost' => round($this->costScore($fee), 2), 'reliability' => round($reliability, 2), 'coverage' => $coverage],
        ];
    }

    protected function deliveryPartner(array $weights, float $fee): array
    {
        $configured = (bool) config('fleetbase.api_key') && (bool) config('fleetbase.base_url');
        $reliability = $this->partnerSuccessRate();

        return [
            'name' => 'delivery_partner',
            'available' => $configured,
            'score' => $configured ? round($this->combine($this->costScore($fee), $reliability, 1.0, $weights), 2) : null,
            'reasons' => $configured
                ? ['Delivery partner is configured.']
                : ['Fleetbase credentials are not configured.'],
            'components' => ['cost' => round($this->costScore($fee), 2), 'reliability' => round($reliability, 2), 'coverage' => 1.0],
        ];
    }

    protected function flatRate(float $orderTotal, array $weights, float $fee): array
    {
        $rate = ShippingRate::where('is_active', 1)
            ->where(function ($query) use ($orderTotal) {
                $query->whereNull('min_order_value')->orWhere('min_order_value', '<=', $orderTotal);
            })
            ->where(function ($query) use ($orderTotal) {
                $query->whereNull('max_order_value')->orWhere('max_order_value', '>=', $orderTotal);
            })
            ->orderBy('rate')
            ->first();

        if (! $rate) {
            return [
                'name' => 'flat_rate',
                'available' => false,
                'score' => null,
                'reasons' => ['No active flat rate matches this order value.'],
                'components' => [],
            ];
        }

        $matchedFee = (float) ($rate->rate ?? 0);
        $freeOver = (float) ($rate->free_over ?? 0);
        if ($freeOver > 0 && $orderTotal >= $freeOver) {
            $matchedFee = 0.0;
        }

        return [
            'name' => 'flat_rate',
            'available' => true,
            'score' => round($this->combine($this->costScore($matchedFee), 0.8, 0.8, $weights), 2),
            'reasons' => ["Flat rate '{$rate->name}' matched".($matchedFee === 0.0 ? ' and is free over the threshold.' : '.')],
            'components' => ['cost' => round($this->costScore($matchedFee), 2), 'reliability' => 0.8, 'coverage' => 0.8],
        ];
    }

    protected function costScore(float $fee): float
    {
        $baseline = max(1.0, (float) ($this->config()['cost_baseline'] ?? 5.0));

        return max(0.0, min(1.0, 1 - ($fee / ($baseline * 2))));
    }

    protected function combine(float $cost, float $reliability, float $coverage, array $weights): float
    {
        return ($cost * $weights['cost']) + ($reliability * $weights['reliability']) + ($coverage * $weights['coverage']);
    }

    protected function vendorFulfilmentRate(int $vendorId): float
    {
        $orders = DB::table('orders')
            ->where('vendor_id', $vendorId)
            ->where('created_at', '>=', Carbon::today()->subDays(90))
            ->get(['status']);

        $total = $orders->count();
        if ($total === 0) {
            return 0.5;
        }

        $good = $orders->whereIn('status', ['delivered', 'completed', 'dispatched', 'ready_for_pickup'])->count();

        return min(1.0, $good / $total);
    }

    protected function partnerSuccessRate(): float
    {
        $shipments = DB::table('fleetbase_shipments')
            ->where('created_at', '>=', Carbon::today()->subDays(90))
            ->get(['error']);

        $total = $shipments->count();
        if ($total === 0) {
            return 0.7;
        }

        $ok = $shipments->filter(fn ($shipment) => empty($shipment->error))->count();

        return min(1.0, $ok / $total);
    }
}
