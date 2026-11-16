<?php

namespace App\Services\Intelligence;

use App\Models\Customer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Algorithm 07 — Customer Retention & Churn.
 *
 * RFM scoring turned into a churn propensity and a projected lifetime value.
 * Recency is measured against the customer's own observed ordering cycle, so a
 * fortnightly buyer is not treated as dormant on the same terms as a weekly
 * one. Every score carries the three raw inputs and the segment it produced.
 */
class RetentionService
{
    public const ALGORITHM_KEY = 'retention';

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
     *     customer_id: int,
     *     recency_days: int,
     *     frequency: int,
     *     monetary: float,
     *     average_order_value: float,
     *     expected_cycle_days: float,
     *     churn_probability: float,
     *     clv_estimate: float,
     *     segment: string
     * }
     */
    public function scoreCustomer(Customer $customer): array
    {
        $config = $this->config();

        $windowDays = max(30, (int) ($config['window_days'] ?? 365));
        $defaultCycle = max(1.0, (float) ($config['default_cycle_days'] ?? 30));
        $dormantDays = max(1, (int) ($config['dormant_days'] ?? 90));
        $newDays = max(1, (int) ($config['new_customer_days'] ?? 30));
        $loyalOrders = max(1, (int) ($config['loyal_min_orders'] ?? 5));
        $horizonDays = max(30, (int) ($config['clv_horizon_days'] ?? 365));

        $from = Carbon::today()->subDays($windowDays);

        $orders = DB::table('orders')
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['processing', 'ready_for_pickup', 'dispatched', 'delivered', 'completed'])
            ->where('payment_status', '!=', 'failed')
            ->orderBy('created_at')
            ->get(['id', 'total_amount', 'created_at']);

        $frequency = $orders->count();
        $monetary = (float) $orders->sum('total_amount');
        $averageOrderValue = $frequency > 0 ? $monetary / $frequency : 0.0;

        $recencyDays = $frequency > 0
            ? (int) Carbon::parse($orders->last()->created_at)->startOfDay()->diffInDays(Carbon::today())
            : $dormantDays;

        $cycle = $this->observedCycleDays($orders->pluck('created_at')->all()) ?? $defaultCycle;

        $overdue = max(0.0, ($recencyDays - $cycle) / max(1.0, $cycle));
        $churnProbability = round(min(1.0, $overdue / 3), 3);

        $ordersPerWindow = $windowDays > 0 ? $frequency / $windowDays : 0.0;
        $projectedOrders = min(50.0, $ordersPerWindow * $horizonDays);
        $clvEstimate = round($projectedOrders * $averageOrderValue, 2);

        $segment = $this->segment(
            $frequency,
            $recencyDays,
            $cycle,
            $dormantDays,
            $newDays,
            $loyalOrders,
            (int) $customer->created_at?->startOfDay()->diffInDays(Carbon::today())
        );

        return [
            'customer_id' => $customer->id,
            'recency_days' => $recencyDays,
            'frequency' => $frequency,
            'monetary' => round($monetary, 2),
            'average_order_value' => round($averageOrderValue, 2),
            'expected_cycle_days' => round($cycle, 2),
            'churn_probability' => $churnProbability,
            'clv_estimate' => $clvEstimate,
            'segment' => $segment,
            'window_start' => $from->toDateString(),
        ];
    }

    /**
     * Customers most likely to churn, highest probability first.
     */
    public function atRisk(int $limit = 25): Collection
    {
        $config = $this->config();
        $dormantDays = max(1, (int) ($config['dormant_days'] ?? 90));

        $ids = DB::table('orders')
            ->whereIn('status', ['processing', 'ready_for_pickup', 'dispatched', 'delivered', 'completed'])
            ->where('payment_status', '!=', 'failed')
            ->where('created_at', '>=', Carbon::today()->subDays($dormantDays * 4))
            ->groupBy('customer_id')
            ->orderByRaw('MAX(created_at) ASC')
            ->limit(max(1, $limit) * 3)
            ->pluck('customer_id')
            ->filter()
            ->unique()
            ->values();

        return Customer::whereIn('id', $ids)->get()
            ->map(fn (Customer $customer) => $this->scoreCustomer($customer))
            ->filter(fn (array $score) => $score['churn_probability'] > 0)
            ->sortByDesc('churn_probability')
            ->take(max(1, $limit))
            ->values();
    }

    /**
     * Median gap in days between consecutive orders, or null with too little
     * history to say anything honest.
     *
     * @param  array<int, string>  $createdAt
     */
    protected function observedCycleDays(array $createdAt): ?float
    {
        if (count($createdAt) < 3) {
            return null;
        }

        $gaps = [];
        for ($i = 1; $i < count($createdAt); $i++) {
            $gaps[] = (float) Carbon::parse($createdAt[$i - 1])->startOfDay()
                ->diffInDays(Carbon::parse($createdAt[$i])->startOfDay());
        }

        $gaps = array_values(array_filter($gaps, fn ($gap) => $gap > 0));
        if (empty($gaps)) {
            return null;
        }

        sort($gaps);
        $count = count($gaps);
        $middle = intdiv($count, 2);

        return $count % 2 === 1
            ? (float) $gaps[$middle]
            : ($gaps[$middle - 1] + $gaps[$middle]) / 2;
    }

    protected function segment(
        int $frequency,
        int $recencyDays,
        float $cycle,
        int $dormantDays,
        int $newDays,
        int $loyalOrders,
        int $accountAgeDays
    ): string {
        if ($frequency === 0 || $recencyDays > $dormantDays) {
            return 'dormant';
        }

        if ($frequency === 1 && $accountAgeDays <= $newDays) {
            return 'new';
        }

        if ($recencyDays > $cycle * 1.5) {
            return 'at_risk';
        }

        if ($frequency >= $loyalOrders && $recencyDays <= $cycle) {
            return 'loyal';
        }

        return 'active';
    }
}
