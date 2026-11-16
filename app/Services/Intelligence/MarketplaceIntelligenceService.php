<?php

namespace App\Services\Intelligence;

use App\Models\AdLedgerEntry;
use App\Models\MarketplaceMetricSnapshot;
use App\Models\Order;
use App\Models\Product;
use App\Models\Refund;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Marketplace Intelligence: computes and stores daily metric snapshots that
 * power the intelligence dashboard and the anomaly detector.
 *
 * Metrics are stored per (metric, scope, scope_id, period_date) so the same
 * framework can carry global, vendor and category level signals.
 */
class MarketplaceIntelligenceService
{
    public const METRICS = [
        'orders' => 'Orders placed',
        'gmv_minor' => 'Gross merchandise value (minor)',
        'new_customers' => 'New customers',
        'new_products' => 'New products published',
        'refund_count' => 'Refunds',
        'refund_minor' => 'Refund value (minor)',
        'ad_spend_minor' => 'Advertising spend (minor)',
        'search_volume' => 'Searches',
        'search_zero_result_rate' => 'Zero-result search rate',
        'search_ctr' => 'Search click-through rate',
        'search_latency_ms' => 'Search p95 latency (ms)',
        'search_indexing_fresh' => 'Search index coverage (fresh docs)',
        'promo_discount_minor' => 'Vendor-funded promotion discount (minor)',
        'juvo_discount_minor' => 'Juvo-funded discount (minor)',
        'promo_discount_rate' => 'Promotion discount rate',
        'agent_new_signups' => 'New sales agents',
        'agent_active_agents' => 'Active sales agents',
        'agent_attributed_orders' => 'Attributed orders',
        'agent_attributed_gmv_minor' => 'Attributed GMV (minor)',
        'agent_commission_accrued_minor' => 'Agent commission accrued (minor)',
        'agent_commission_paid_minor' => 'Agent commission paid (minor)',
        'agent_payout_pending_minor' => 'Agent payouts awaiting (minor)',
    ];

    /**
     * Snapshot a single day.
     */
    public function snapshot(Carbon $date): int
    {
        $day = $date->copy()->startOfDay();
        $end = $day->copy()->endOfDay()->addSecond();
        $written = 0;

        $orders = Order::whereBetween('created_at', [$day, $end]);
        $paidOrders = (clone $orders)->whereNotIn('status', ['canceled', 'returned']);

        $written += $this->put('orders', 'global', null, $day, (clone $orders)->count(), (clone $orders)->count());
        $written += $this->put('gmv_minor', 'global', null, $day, $this->minor((float) (clone $paidOrders)->sum('total_amount')), (clone $paidOrders)->count());
        $written += $this->put('new_customers', 'global', null, $day, DB::table('customers')->whereBetween('created_at', [$day, $end])->count(), 0);
        $written += $this->put('new_products', 'global', null, $day, Product::whereBetween('created_at', [$day, $end])->count(), 0);

        $refunds = Refund::whereBetween('created_at', [$day, $end]);
        $written += $this->put('refund_count', 'global', null, $day, (clone $refunds)->count(), (clone $refunds)->count());
        $written += $this->put('refund_minor', 'global', null, $day, $this->refundMinor($refunds), 0);

        $written += $this->put('ad_spend_minor', 'global', null, $day, $this->adSpendMinor($day, $end), 0);

        foreach ($this->searchMetrics($day, $end) as $metric => [$value, $sample]) {
            $written += $this->put($metric, 'global', null, $day, $value, $sample);
        }

        foreach ($this->pricingMetrics($day, $end) as $metric => [$value, $sample]) {
            $written += $this->put($metric, 'global', null, $day, $value, $sample);
        }

        foreach ($this->agentMetrics($day, $end) as $metric => [$value, $sample]) {
            $written += $this->put($metric, 'global', null, $day, $value, $sample);
        }

        return $written;
    }

    /**
     * Sales-agent network KPIs derived from the agent ledger.
     *
     * @return array<string, array{0:float|int,1:int}>
     */
    protected function agentMetrics(Carbon $day, Carbon $end): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('agent_commission_entries')) {
            return [];
        }

        $signups = \Illuminate\Support\Facades\DB::table('sales_reps')
            ->whereBetween('created_at', [$day, $end])->count();

        $active = \Illuminate\Support\Facades\DB::table('sales_reps')
            ->whereIn('status', ['approved', 'active'])->count();

        $attributions = \Illuminate\Support\Facades\DB::table('sales_attributions')
            ->whereBetween('created_at', [$day, $end]);

        $attributedOrders = (clone $attributions)->count();
        $attributedGmv = (float) (clone $attributions)->sum('order_total');

        $entries = \Illuminate\Support\Facades\DB::table('agent_commission_entries');
        $accrued = (float) (clone $entries)->whereIn('state', ['eligible', 'approved', 'payable'])->sum('amount');
        $paid = (float) (clone $entries)->where('state', 'paid')->sum('amount');

        $pendingPayouts = 0.0;
        if (\Illuminate\Support\Facades\Schema::hasTable('agent_payout_requests')) {
            $pendingPayouts = (float) \Illuminate\Support\Facades\DB::table('agent_payout_requests')
                ->whereIn('status', ['requested', 'approved', 'processing'])->sum('amount');
        }

        return [
            'agent_new_signups' => [$signups, $signups],
            'agent_active_agents' => [$active, $active],
            'agent_attributed_orders' => [$attributedOrders, $attributedOrders],
            'agent_attributed_gmv_minor' => [$this->minor($attributedGmv), $attributedOrders],
            'agent_commission_accrued_minor' => [$this->minor($accrued), 0],
            'agent_commission_paid_minor' => [$this->minor($paid), 0],
            'agent_payout_pending_minor' => [$this->minor($pendingPayouts), 0],
        ];
    }

    /**
     * Pricing/promotion KPIs derived from persisted order price snapshots.
     *
     * @return array<string, array{0:float|int,1:int}>
     */
    protected function pricingMetrics(Carbon $day, Carbon $end): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('orders', 'vendor_funded_discount')) {
            return [];
        }

        $orders = Order::whereBetween('created_at', [$day, $end]);

        $vendorFunded = (float) (clone $orders)->sum('vendor_funded_discount');
        $juvoFunded = (float) (clone $orders)->sum('juvo_funded_discount');
        $discounted = (int) (clone $orders)->where('discount_amount', '>', 0)->count();
        $total = (int) (clone $orders)->count();
        $rate = $total > 0 ? $discounted / $total : 0.0;

        return [
            'promo_discount_minor' => [$this->minor($vendorFunded), $total],
            'juvo_discount_minor' => [$this->minor($juvoFunded), $total],
            'promo_discount_rate' => [$rate, $total],
        ];
    }

    public function backfill(int $days = 30): int
    {
        $count = 0;
        for ($i = $days; $i >= 0; $i--) {
            $count += $this->snapshot(now()->subDays($i));
        }

        return $count;
    }

    /**
     * Search-engine KPIs derived from the real query log and behaviour stream.
     *
     * @return array<string, array{0:float|int,1:int}>
     */
    protected function searchMetrics(Carbon $day, Carbon $end): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('search_queries')) {
            return [];
        }

        $queries = \Illuminate\Support\Facades\DB::table('search_queries')
            ->whereBetween('occurred_at', [$day, $end])
            ->where('surface', 'search_results');

        $total = (clone $queries)->count();
        $zero = (clone $queries)->where('is_zero_result', true)->count();
        $zeroRate = $total > 0 ? $zero / $total : 0.0;

        $latencies = (clone $queries)->orderBy('latency_ms')->pluck('latency_ms')->map(fn ($v) => (int) $v)->all();
        $p95 = 0;
        if (! empty($latencies)) {
            $index = (int) floor(0.95 * (count($latencies) - 1));
            $p95 = $latencies[$index];
        }

        $events = \Illuminate\Support\Facades\DB::table('recommendation_events')
            ->where('algorithm_key', 'search_ranking')
            ->where('is_valid', true)
            ->whereBetween('occurred_at', [$day, $end]);

        $clicks = (int) (clone $events)->where('type', 'click')->count();
        $impressions = (int) (clone $events)->where('type', 'impression')->count();
        $ctr = $impressions > 0 ? $clicks / $impressions : 0.0;

        $fresh = 0;
        $docs = 0;
        if (\Illuminate\Support\Facades\Schema::hasTable('product_search_documents')) {
            $docs = \Illuminate\Support\Facades\DB::table('product_search_documents')->count();
            $fresh = \Illuminate\Support\Facades\DB::table('product_search_documents')
                ->where('refreshed_at', '>=', $day->copy()->subDay())
                ->count();
        }

        return [
            'search_volume' => [$total, $total],
            'search_zero_result_rate' => [$zeroRate, $total],
            'search_ctr' => [$ctr, $impressions],
            'search_latency_ms' => [$p95, $total],
            'search_indexing_fresh' => [$fresh, $docs],
        ];
    }

    protected function put(string $metric, string $scope, ?int $scopeId, Carbon $date, float|int $value, int $sample): int
    {
        MarketplaceMetricSnapshot::updateOrCreate(
            [
                'metric' => $metric,
                'scope' => $scope,
                'scope_id' => $scopeId,
                'period_date' => $date->toDateString(),
            ],
            [
                'value' => $value,
                'sample_size' => $sample,
            ]
        );

        return 1;
    }

    protected function minor(float $amount): int
    {
        return (int) round($amount * 100);
    }

    protected function refundMinor($refunds): int
    {
        $column = \Illuminate\Support\Facades\Schema::hasColumn('refunds', 'amount') ? 'amount' : 'total_amount';

        return $this->minor((float) (clone $refunds)->sum($column));
    }

    protected function adSpendMinor(Carbon $day, Carbon $end): int
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('ad_ledger_entries')) {
            return 0;
        }

        return (int) AdLedgerEntry::where('direction', 'debit')
            ->whereBetween('created_at', [$day, $end])
            ->sum('amount_minor');
    }

    /**
     * Series of snapshots for a metric used by the anomaly detector.
     *
     * @return array<int, float>
     */
    public function series(string $metric, string $scope, ?int $scopeId, Carbon $before, int $window): array
    {
        return MarketplaceMetricSnapshot::query()
            ->where('metric', $metric)
            ->where('scope', $scope)
            ->where('scope_id', $scopeId)
            ->where('period_date', '<', $before->toDateString())
            ->orderByDesc('period_date')
            ->limit($window)
            ->pluck('value')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    public function latest(string $metric, ?string $scope = 'global'): ?MarketplaceMetricSnapshot
    {
        return MarketplaceMetricSnapshot::query()
            ->where('metric', $metric)
            ->when($scope, fn ($q) => $q->where('scope', $scope))
            ->orderByDesc('period_date')
            ->first();
    }
}
