<?php

namespace App\Services\Intelligence;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Algorithm 06 — Fraud & Risk Detection.
 *
 * Explainable, additive risk scoring over six independent signals. Every
 * signal reports the points it contributed and why, so a reviewer can
 * challenge a score instead of trusting a black box. Nothing here blocks an
 * order by itself — it produces a band for a human to act on.
 */
class FraudDetectionService
{
    public const ALGORITHM_KEY = 'fraud_detection';

    public const BANDS = ['low', 'medium', 'high', 'critical'];

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
     *     score: int,
     *     band: string,
     *     value: float,
     *     signals: array<int, array{name: string, points: int, detail: string}>
     * }
     */
    public function scoreOrder(Order $order): array
    {
        $config = $this->config();
        $weights = array_merge([
            'order_value' => 25,
            'velocity' => 20,
            'account_age' => 15,
            'payment_state' => 20,
            'refund_history' => 10,
            'guest' => 10,
        ], (array) ($config['weights'] ?? []));

        $value = (float) $order->total_amount;
        $threshold = $this->highValueThreshold();

        $signals = [];
        $score = 0;

        if ($threshold > 0 && $value >= $threshold) {
            $points = (int) $weights['order_value'];
            $score += $points;
            $signals[] = [
                'name' => 'order_value',
                'points' => $points,
                'detail' => sprintf('Order total %s is at or above the high-value threshold %s.', number_format($value, 2), number_format($threshold, 2)),
            ];
        }

        $recentOrders = $order->customer_id ? $this->ordersInWindow((int) $order->customer_id, 1) : 0;
        $velocityLimit = max(1, (int) ($config['velocity_window_orders'] ?? 3));
        if ($order->customer_id && $recentOrders >= $velocityLimit) {
            $points = (int) $weights['velocity'];
            $score += $points;
            $signals[] = [
                'name' => 'velocity',
                'points' => $points,
                'detail' => "{$recentOrders} orders placed by this customer in the last 24 hours.",
            ];
        }

        $accountAgeDays = $this->accountAgeDays($order);
        $newAccountDays = max(1, (int) ($config['new_account_days'] ?? 7));
        if ($accountAgeDays !== null && $accountAgeDays <= $newAccountDays && $value > 0 && ($threshold <= 0 || $value >= $threshold / 2)) {
            $points = (int) $weights['account_age'];
            $score += $points;
            $signals[] = [
                'name' => 'account_age',
                'points' => $points,
                'detail' => "Customer account is {$accountAgeDays} day(s) old and placed a high-value order.",
            ];
        }

        $failedPayment = in_array($order->payment_status, ['failed', 'refunded', 'partially_refunded'], true)
            && ! in_array($order->status, ['pending', 'canceled', 'returned'], true);
        if ($failedPayment) {
            $points = (int) $weights['payment_state'];
            $score += $points;
            $signals[] = [
                'name' => 'payment_state',
                'points' => $points,
                'detail' => "Payment is '{$order->payment_status}' yet the order progressed to '{$order->status}'.",
            ];
        }

        $refunds = $order->customer_id ? $this->refundsForCustomer((int) $order->customer_id) : 0;
        $refundLimit = max(1, (int) ($config['refund_history_limit'] ?? 1));
        if ($refunds >= $refundLimit) {
            $points = (int) $weights['refund_history'];
            $score += $points;
            $signals[] = [
                'name' => 'refund_history',
                'points' => $points,
                'detail' => "Customer has {$refunds} prior refund(s).",
            ];
        }

        $halfThreshold = $threshold > 0 ? $threshold / 2 : 0.0;
        if ($order->is_guest && $halfThreshold > 0 && $value >= $halfThreshold) {
            $points = (int) $weights['guest'];
            $score += $points;
            $signals[] = [
                'name' => 'guest',
                'points' => $points,
                'detail' => 'Guest checkout above half the high-value threshold.',
            ];
        }

        $score = max(0, min(100, $score));

        return [
            'order_id' => $order->id,
            'score' => $score,
            'band' => $this->band($score, $config),
            'value' => $value,
            'signals' => $signals,
        ];
    }

    /**
     * Highest-risk orders in the window, highest score first.
     */
    public function flagged(int $limit = 25, int $days = 30): Collection
    {
        $orders = Order::with('customer')
            ->where('created_at', '>=', Carbon::today()->subDays($days))
            ->orderByDesc('total_amount')
            ->limit(max(1, $limit) * 4)
            ->get();

        return $orders
            ->map(fn (Order $order) => $this->scoreOrder($order))
            ->filter(fn (array $score) => $score['band'] !== 'low')
            ->sortByDesc('score')
            ->take(max(1, $limit))
            ->values();
    }

    protected function band(int $score, array $config): string
    {
        $medium = (int) ($config['medium_threshold'] ?? 30);
        $high = (int) ($config['high_threshold'] ?? 60);
        $critical = (int) ($config['critical_threshold'] ?? 85);

        if ($score >= $critical) {
            return 'critical';
        }
        if ($score >= $high) {
            return 'high';
        }
        if ($score >= $medium) {
            return 'medium';
        }

        return 'low';
    }

    protected function highValueThreshold(): float
    {
        $configured = (float) ($this->config()['high_value_threshold'] ?? 0);
        if ($configured > 0) {
            return $configured;
        }

        $p95 = Order::where('created_at', '>=', Carbon::today()->subDays(90))
            ->where('payment_status', '!=', 'failed')
            ->orderByDesc('total_amount')
            ->offset((int) (Order::where('created_at', '>=', Carbon::today()->subDays(90))->count() * 0.05))
            ->value('total_amount');

        return (float) ($p95 ?? 0);
    }

    protected function ordersInWindow(int $customerId, int $hours): int
    {
        return (int) Order::where('customer_id', $customerId)
            ->where('created_at', '>=', Carbon::now()->subHours(max(1, $hours)))
            ->count();
    }

    protected function refundsForCustomer(int $customerId): int
    {
        return (int) DB::table('refunds')
            ->where('customer_id', $customerId)
            ->whereIn('status', ['completed', 'approved', 'pending', 'succeeded', 'paid'])
            ->count();
    }

    protected function accountAgeDays(Order $order): ?int
    {
        if (! $order->customer_id) {
            return null;
        }

        $created = Customer::whereKey($order->customer_id)->value('created_at');
        if (! $created) {
            return null;
        }

        return (int) Carbon::parse($created)->startOfDay()->diffInDays(Carbon::today());
    }
}
