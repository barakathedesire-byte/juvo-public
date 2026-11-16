<?php

namespace App\Services\Intelligence;

use App\Models\MarketplaceAnomaly;
use App\Models\MarketplaceAnomalyRule;
use App\Models\MarketplaceMetricSnapshot;
use Illuminate\Support\Carbon;

/**
 * Explainable, baseline-driven anomaly detection. No black boxes: every rule
 * declares the metric, the statistical method, the window and a minimum sample
 * size. Detection is strictly read-only — remediating anomalies is a separate,
 * authorised, human decision.
 */
class AnomalyDetectionService
{
    public function __construct(
        protected MarketplaceIntelligenceService $intelligence,
    ) {}

    /**
     * @return array<string, array<string, mixed>>
     */
    public function definitions(): array
    {
        return [
            'orders_drop' => [
                'name' => 'Orders drop',
                'metric' => 'orders',
                'method' => 'percent_change',
                'severity' => 'warning',
                'window' => 14,
                'min_sample' => 7,
                'config' => ['direction' => 'below', 'threshold' => 0.4],
            ],
            'gmv_drop' => [
                'name' => 'GMV drop',
                'metric' => 'gmv_minor',
                'method' => 'percent_change',
                'severity' => 'critical',
                'window' => 14,
                'min_sample' => 7,
                'config' => ['direction' => 'below', 'threshold' => 0.4],
            ],
            'refund_spike' => [
                'name' => 'Refund spike',
                'metric' => 'refund_count',
                'method' => 'zscore',
                'severity' => 'critical',
                'window' => 21,
                'min_sample' => 10,
                'config' => ['direction' => 'above', 'threshold' => 2.5],
            ],
            'new_customers_drop' => [
                'name' => 'New customer slowdown',
                'metric' => 'new_customers',
                'method' => 'percent_change',
                'severity' => 'warning',
                'window' => 14,
                'min_sample' => 7,
                'config' => ['direction' => 'below', 'threshold' => 0.5],
            ],
            'ad_spend_spike' => [
                'name' => 'Advertising spend spike',
                'metric' => 'ad_spend_minor',
                'method' => 'percent_change',
                'severity' => 'warning',
                'window' => 14,
                'min_sample' => 7,
                'config' => ['direction' => 'above', 'threshold' => 1.0],
            ],
            'search_zero_result_spike' => [
                'name' => 'Zero-result searches spike',
                'metric' => 'search_zero_result_rate',
                'method' => 'zscore',
                'severity' => 'warning',
                'window' => 14,
                'min_sample' => 7,
                'config' => ['direction' => 'above', 'threshold' => 2.0],
            ],
            'search_ctr_drop' => [
                'name' => 'Search click-through drop',
                'metric' => 'search_ctr',
                'method' => 'percent_change',
                'severity' => 'warning',
                'window' => 14,
                'min_sample' => 7,
                'config' => ['direction' => 'below', 'threshold' => 0.4],
            ],
            'search_latency_spike' => [
                'name' => 'Search latency spike',
                'metric' => 'search_latency_ms',
                'method' => 'zscore',
                'severity' => 'critical',
                'window' => 14,
                'min_sample' => 7,
                'config' => ['direction' => 'above', 'threshold' => 3.0],
            ],
            'search_indexing_stale' => [
                'name' => 'Search index freshness drop',
                'metric' => 'search_indexing_fresh',
                'method' => 'percent_change',
                'severity' => 'warning',
                'window' => 14,
                'min_sample' => 5,
                'config' => ['direction' => 'below', 'threshold' => 0.5],
            ],
            'promotion_discount_spike' => [
                'name' => 'Vendor-funded discount spike',
                'metric' => 'promo_discount_minor',
                'method' => 'percent_change',
                'severity' => 'warning',
                'window' => 14,
                'min_sample' => 7,
                'config' => ['direction' => 'above', 'threshold' => 1.0],
            ],
            'juvo_funded_spike' => [
                'name' => 'Juvo-funded discount spike',
                'metric' => 'juvo_discount_minor',
                'method' => 'percent_change',
                'severity' => 'critical',
                'window' => 14,
                'min_sample' => 7,
                'config' => ['direction' => 'above', 'threshold' => 1.0],
            ],
            'promotion_discount_rate_spike' => [
                'name' => 'Discount-rate spike',
                'metric' => 'promo_discount_rate',
                'method' => 'percent_change',
                'severity' => 'warning',
                'window' => 14,
                'min_sample' => 7,
                'config' => ['direction' => 'above', 'threshold' => 0.75],
            ],
        ];
    }

    public function seedDefaultRules(): int
    {
        $count = 0;
        foreach ($this->definitions() as $key => $def) {
            MarketplaceAnomalyRule::updateOrCreate(
                ['key' => $key],
                [
                    'name' => $def['name'],
                    'metric' => $def['metric'],
                    'method' => $def['method'],
                    'severity' => $def['severity'],
                    'window' => $def['window'],
                    'min_sample' => $def['min_sample'],
                    'config' => $def['config'],
                ]
            );
            $count++;
        }

        return $count;
    }

    /**
     * Evaluate every enabled rule for a given day and persist new anomalies.
     *
     * @return array{detected:int, evaluated:int}
     */
    public function detect(Carbon $date): array
    {
        $date = $date->copy()->startOfDay();
        $detected = 0;
        $evaluated = 0;

        foreach (MarketplaceAnomalyRule::where('is_enabled', true)->get() as $rule) {
            $evaluated++;
            $anomaly = $this->evaluate($rule, $date);
            if ($anomaly) {
                $detected++;
            }
        }

        return ['detected' => $detected, 'evaluated' => $evaluated];
    }

    protected function evaluate(MarketplaceAnomalyRule $rule, Carbon $date): ?MarketplaceAnomaly
    {
        $scope = $rule->scope ?: 'global';
        $scopeId = null;

        $observed = MarketplaceMetricSnapshot::where('metric', $rule->metric)
            ->where('scope', $scope)
            ->where('scope_id', $scopeId)
            ->where('period_date', $date->toDateString())
            ->value('value');

        if ($observed === null) {
            return null;
        }

        $series = $this->intelligence->series($rule->metric, $scope, $scopeId, $date, (int) $rule->window);
        $threshold = (int) $rule->min_sample;

        if ($rule->method !== 'threshold' && count($series) < $threshold) {
            return null;
        }
        if (empty($series)) {
            return null;
        }

        $observed = (float) $observed;
        $config = $rule->config ?? [];
        $direction = $config['direction'] ?? 'above';

        [$hit, $expected, $deviation] = $this->applyMethod($rule->method, $series, $observed, $config, $direction);

        if (! $hit) {
            return null;
        }

        $dedupKey = implode('.', [$rule->key, $scope, $scopeId ?? '0', $date->toDateString()]);

        return MarketplaceAnomaly::firstOrCreate(
            ['dedup_key' => $dedupKey],
            [
                'rule_id' => $rule->id,
                'metric' => $rule->metric,
                'scope' => $scope,
                'scope_id' => $scopeId,
                'observed_value' => $observed,
                'expected_value' => $expected,
                'deviation' => $deviation,
                'severity' => $rule->severity,
                'status' => 'open',
                'meta' => [
                    'method' => $rule->method,
                    'direction' => $direction,
                    'window' => (int) $rule->window,
                    'sample_size' => count($series),
                ],
                'detected_at' => now(),
            ]
        );
    }

    /**
     * @param  array<int, float>  $series
     * @param  array<string, mixed>  $config
     * @return array{0:bool,1:float,2:float}
     */
    protected function applyMethod(string $method, array $series, float $observed, array $config, string $direction): array
    {
        switch ($method) {
            case 'threshold':
                $value = (float) ($config['value'] ?? 0);
                $hit = $direction === 'below' ? $observed < $value : $observed > $value;

                return [$hit, $value, $value != 0 ? ($observed - $value) / $value : $observed];

            case 'zscore':
                $mean = $this->mean($series);
                $std = $this->stddev($series, $mean);
                $z = $std > 0 ? ($observed - $mean) / $std : 0.0;
                $target = (float) ($config['threshold'] ?? 2.5);
                $hit = $direction === 'below' ? $z <= -abs($target) : $z >= abs($target);

                return [$hit, $mean, round($z, 4)];

            case 'iqr':
                $sorted = $series;
                sort($sorted);
                $q1 = $this->quantile($sorted, 0.25);
                $q3 = $this->quantile($sorted, 0.75);
                $iqr = $q3 - $q1;
                $k = (float) ($config['multiplier'] ?? 1.5);
                $low = $q1 - $k * $iqr;
                $high = $q3 + $k * $iqr;
                $hit = $direction === 'below' ? $observed < $low : $observed > $high;
                $expected = $this->median($sorted);

                return [$hit, $expected, $expected != 0 ? ($observed - $expected) / $expected : $observed];

            case 'percent_change':
            default:
                $expected = $this->median($series);
                $deviation = $expected != 0 ? ($observed - $expected) / $expected : ($observed > 0 ? 1.0 : 0.0);
                $target = (float) ($config['threshold'] ?? 0.4);
                $hit = $direction === 'below' ? $deviation <= -abs($target) : $deviation >= abs($target);

                return [$hit, $expected, round($deviation, 4)];
        }
    }

    protected function mean(array $values): float
    {
        return count($values) ? array_sum($values) / count($values) : 0.0;
    }

    protected function stddev(array $values, float $mean): float
    {
        if (count($values) < 2) {
            return 0.0;
        }
        $variance = array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $values)) / (count($values) - 1);

        return sqrt($variance);
    }

    protected function median(array $values): float
    {
        if (! $values) {
            return 0.0;
        }
        $sorted = $values;
        sort($sorted);
        $count = count($sorted);
        $mid = intdiv($count, 2);

        return $count % 2 ? (float) $sorted[$mid] : ((float) $sorted[$mid - 1] + (float) $sorted[$mid]) / 2;
    }

    protected function quantile(array $sorted, float $q): float
    {
        if (! $sorted) {
            return 0.0;
        }
        $pos = ($count = count($sorted)) > 1 ? $q * ($count - 1) : 0;
        $base = (int) floor($pos);
        $rest = $pos - $base;

        if (! isset($sorted[$base + 1])) {
            return (float) $sorted[$base];
        }

        return (float) $sorted[$base] + $rest * ((float) $sorted[$base + 1] - (float) $sorted[$base]);
    }
}
