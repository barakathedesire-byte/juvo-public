<?php

namespace App\Services\Intelligence;

use App\Models\Vendor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Algorithm 08 — Vendor Performance Scoring.
 *
 * A 0–100 composite of the five signals a marketplace actually cares about:
 * fulfilment, cancellations, on-time delivery, product quality and support
 * load. Every component is a rate computed from real orders, so a vendor with
 * three perfect orders is graded on a thinner base than one with three hundred
 * — the confidence term makes that explicit instead of hiding it.
 */
class VendorPerformanceService
{
    public const ALGORITHM_KEY = 'vendor_performance';

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
     *     vendor_id: int,
     *     score: float,
     *     grade: string,
     *     confidence: float,
     *     components: array<string, array{value: float, weight: float, points: float}>
     * }
     */
    public function scoreVendor(Vendor $vendor): array
    {
        $config = $this->config();
        $days = max(30, (int) ($config['window_days'] ?? 90));
        $onTimeDays = max(1, (int) ($config['on_time_days'] ?? 7));

        $weights = array_merge([
            'fulfilment' => 0.30,
            'on_time' => 0.25,
            'quality' => 0.20,
            'cancellation' => 0.15,
            'support_load' => 0.10,
        ], (array) ($config['weights'] ?? []));

        $from = Carbon::today()->subDays($days);

        $orders = DB::table('orders')
            ->where('vendor_id', $vendor->id)
            ->where('created_at', '>=', $from)
            ->get(['id', 'status', 'created_at', 'fulfilled_at', 'delivered_at']);

        $total = $orders->count();
        $completed = $orders->whereIn('status', ['delivered', 'completed'])->count();
        $canceled = $orders->whereIn('status', ['canceled', 'returned'])->count();
        $evaluable = max(0, $total - $canceled);

        $fulfilmentRate = $evaluable > 0 ? $completed / $evaluable : 0.0;

        $delivered = $orders->filter(fn ($order) => in_array($order->status, ['delivered', 'completed'], true));
        $onTime = $delivered->filter(function ($order) use ($onTimeDays) {
            $endedAt = $order->delivered_at ?: $order->fulfilled_at;
            if (! $endedAt) {
                return false;
            }

            return Carbon::parse($order->created_at)->diffInDays(Carbon::parse($endedAt)) <= $onTimeDays;
        })->count();
        $onTimeRate = $delivered->count() > 0 ? $onTime / $delivered->count() : 0.0;

        $avgRating = (float) DB::table('product_reviews')
            ->join('products', 'products.id', '=', 'product_reviews.product_id')
            ->where('products.vendor_id', $vendor->id)
            ->where('product_reviews.is_approved', 1)
            ->where('product_reviews.created_at', '>=', $from)
            ->avg('product_reviews.rating');
        $qualityRate = $avgRating > 0 ? min(1.0, $avgRating / 5) : 0.0;

        $cancellationRate = $total > 0 ? $canceled / $total : 0.0;
        $supportRate = $total > 0
            ? min(1.0, DB::table('support_tickets')->where('vendor_id', $vendor->id)->where('created_at', '>=', $from)->count() / $total)
            : 0.0;

        $components = [
            'fulfilment' => ['value' => round($fulfilmentRate, 4), 'weight' => $weights['fulfilment']],
            'on_time' => ['value' => round($onTimeRate, 4), 'weight' => $weights['on_time']],
            'quality' => ['value' => round($qualityRate, 4), 'weight' => $weights['quality']],
            'cancellation' => ['value' => round(1 - $cancellationRate, 4), 'weight' => $weights['cancellation']],
            'support_load' => ['value' => round(1 - $supportRate, 4), 'weight' => $weights['support_load']],
        ];

        $score = 0.0;
        foreach ($components as $key => &$component) {
            $component['points'] = round($component['value'] * $component['weight'] * 100, 2);
            $score += $component['points'];
        }
        unset($component);

        $score = round(max(0.0, min(100.0, $score)), 2);

        return [
            'vendor_id' => $vendor->id,
            'score' => $score,
            'grade' => $this->grade($score),
            'confidence' => $this->confidence($total, (int) ($config['confidence_order_count'] ?? 20)),
            'orders_evaluated' => $total,
            'components' => $components,
        ];
    }

    /**
     * Vendors ranked by score, weakest first so the list is actionable.
     */
    public function leaderboard(int $limit = 25): Collection
    {
        return Vendor::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->get()
            ->map(fn (Vendor $vendor) => $this->scoreVendor($vendor))
            ->filter(fn (array $score) => $score['orders_evaluated'] > 0)
            ->sortBy('score')
            ->take(max(1, $limit))
            ->values();
    }

    protected function grade(float $score): string
    {
        return match (true) {
            $score >= 90 => 'A',
            $score >= 80 => 'B',
            $score >= 70 => 'C',
            $score >= 60 => 'D',
            default => 'E',
        };
    }

    protected function confidence(int $ordersEvaluated, int $target): float
    {
        return round(min(1.0, $ordersEvaluated / max(1, $target)), 2);
    }
}
