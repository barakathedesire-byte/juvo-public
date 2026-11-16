<?php

namespace App\Services\Intelligence;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Algorithm 05 — Demand Forecasting.
 *
 * Deterministic SKU / category / catalog demand projection: a least-squares
 * trend over daily units, modulated by a day-of-week seasonality index derived
 * from the same history. No model training, no external service — the same
 * input always yields the same forecast, and every output carries the basis it
 * was computed from so an operator can audit it.
 */
class DemandForecastService
{
    public const ALGORITHM_KEY = 'demand_forecasting';

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
     *     basis: array<string, int|float>,
     *     days: array<int, array{date: string, units: float}>,
     *     confidence: float
     * }
     */
    public function forecast(?int $productId = null, ?int $categoryId = null, ?int $daysAhead = null): array
    {
        $config = $this->config();

        $lookback = max(7, (int) ($config['lookback_days'] ?? 60));
        $ahead = max(1, (int) ($daysAhead ?? $config['days_ahead'] ?? 7));
        $minHistory = max(1, (int) ($config['min_history_days'] ?? 7));
        $seasonal = (bool) ($config['seasonality'] ?? true);
        $safety = max(0.0, (float) ($config['safety_stock_percent'] ?? 0));

        $series = $this->dailyUnits($lookback, $productId, $categoryId);

        $total = $series->sum();
        $activeDays = max(1, $series->count());
        $daysWithData = (int) $series->filter(fn ($units) => $units > 0)->count();
        $average = $total / $activeDays;
        $slope = $this->slope($series->values()->all());
        $weekdayIndex = $seasonal ? $this->weekdayIndex($lookback, $productId, $categoryId) : [];

        $days = [];
        $start = Carbon::today();

        for ($offset = 1; $offset <= $ahead; $offset++) {
            $date = $start->copy()->addDays($offset);
            $base = $average * max(0.0, 1 + ($slope * $offset));
            $factor = $seasonal ? ($weekdayIndex[$date->dayOfWeekIso] ?? 1.0) : 1.0;

            $days[] = [
                'date' => $date->toDateString(),
                'units' => round($base * $factor * (1 + $safety / 100), 2),
            ];
        }

        return [
            'basis' => [
                'lookback_days' => $lookback,
                'days_observed' => $activeDays,
                'days_with_sales' => $daysWithData,
                'total_units' => (int) $total,
                'average_daily_units' => round($average, 3),
                'trend_slope_per_day' => round($slope, 5),
            ],
            'days' => $days,
            'confidence' => $this->confidence((int) $total, $daysWithData, $minHistory),
        ];
    }

    /**
     * @return Collection<int, float>
     */
    protected function dailyUnits(int $lookback, ?int $productId, ?int $categoryId): Collection
    {
        $query = DB::table('order_details')
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->whereIn('orders.status', ['processing', 'ready_for_pickup', 'dispatched', 'delivered', 'completed'])
            ->where('orders.payment_status', '!=', 'failed')
            ->where('orders.created_at', '>=', Carbon::today()->subDays($lookback))
            ->selectRaw('DATE(orders.created_at) AS day, SUM(order_details.quantity) AS units')
            ->groupByRaw('DATE(orders.created_at)');

        if ($productId !== null) {
            $query->where('order_details.product_id', $productId);
        }

        if ($categoryId !== null) {
            $query->join('products', 'products.id', '=', 'order_details.product_id')
                ->where('products.category_id', $categoryId);
        }

        $byDay = $query->pluck('units', 'day');

        $series = collect();
        for ($offset = $lookback; $offset >= 0; $offset--) {
            $series->push((float) ($byDay[Carbon::today()->subDays($offset)->toDateString()] ?? 0));
        }

        return $series;
    }

    /**
     * Day-of-week index: average units for that weekday divided by the overall
     * average. 1.0 means "an ordinary day for this catalog".
     *
     * @return array<int, float>
     */
    protected function weekdayIndex(int $lookback, ?int $productId, ?int $categoryId): array
    {
        $from = Carbon::today()->subDays($lookback);

        $rows = DB::table('order_details')
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->whereIn('orders.status', ['processing', 'ready_for_pickup', 'dispatched', 'delivered', 'completed'])
            ->where('orders.payment_status', '!=', 'failed')
            ->where('orders.created_at', '>=', $from)
            ->selectRaw('DAYOFWEEK(orders.created_at) AS dow, SUM(order_details.quantity) AS total_units, COUNT(DISTINCT DATE(orders.created_at)) AS days_seen')
            ->groupByRaw('DAYOFWEEK(orders.created_at)');

        if ($productId !== null) {
            $rows->where('order_details.product_id', $productId);
        }

        if ($categoryId !== null) {
            $rows->join('products', 'products.id', '=', 'order_details.product_id')
                ->where('products.category_id', $categoryId);
        }

        $averages = [];
        foreach ($rows->get() as $row) {
            $averages[$this->isoDayOfWeek((int) $row->dow)] = (float) $row->total_units / max(1, (int) $row->days_seen);
        }

        $overall = count($averages) > 0 ? array_sum($averages) / count($averages) : 0.0;
        if ($overall <= 0) {
            return [];
        }

        $index = [];
        foreach ($averages as $isoDay => $average) {
            $index[$isoDay] = round($average / $overall, 3);
        }

        return $index;
    }

    /**
     * MySQL DAYOFWEEK: 1 = Sunday … 7 = Saturday. ISO: 1 = Monday … 7 = Sunday.
     */
    protected function isoDayOfWeek(int $mysqlDay): int
    {
        return $mysqlDay === 1 ? 7 : $mysqlDay - 1;
    }

    /**
     * Least-squares slope of units against day index.
     *
     * @param  array<int, float>  $series
     */
    protected function slope(array $series): float
    {
        $n = count($series);
        if ($n < 2) {
            return 0.0;
        }

        $sumX = 0.0;
        $sumY = 0.0;
        $sumXY = 0.0;
        $sumXX = 0.0;

        foreach ($series as $i => $y) {
            $sumX += $i;
            $sumY += $y;
            $sumXY += $i * $y;
            $sumXX += $i * $i;
        }

        $denominator = ($n * $sumXX) - ($sumX * $sumX);
        if ($denominator == 0.0) {
            return 0.0;
        }

        return (($n * $sumXY) - ($sumX * $sumY)) / $denominator;
    }

    protected function confidence(int $totalUnits, int $daysWithData, int $minHistory): float
    {
        $coverage = min(1.0, $daysWithData / $minHistory);
        $volume = min(1.0, $totalUnits / 10);

        return round($coverage * $volume, 2);
    }
}
