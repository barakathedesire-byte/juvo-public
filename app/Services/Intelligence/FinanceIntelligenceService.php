<?php

namespace App\Services\Intelligence;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Algorithm 11 — Finance Intelligence.
 *
 * Ledger, payout and margin analytics over a rolling window: what the platform
 * took, what vendors are owed, how long payouts are sitting, and how
 * concentrated revenue has become. Concentration is reported as an HHI so a
 * slow drift toward single-vendor dependence is visible long before it hurts.
 */
class FinanceIntelligenceService
{
    public const ALGORITHM_KEY = 'finance_intelligence';

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
     * @return array<string, mixed>
     */
    public function report(?int $days = null): array
    {
        $config = $this->config();

        $windowDays = max(1, (int) ($days ?? $config['window_days'] ?? 30));
        $ageingDays = max(1, (int) ($config['payout_ageing_days'] ?? 14));
        $marginFloor = (float) ($config['min_margin_percent'] ?? 0) / 100;
        $concentrationThreshold = (float) ($config['concentration_threshold'] ?? 0.40);

        $from = Carbon::today()->subDays($windowDays);

        $orders = DB::table('orders')
            ->where('created_at', '>=', $from)
            ->whereNotIn('status', ['canceled', 'returned'])
            ->where('payment_status', '!=', 'failed')
            ->get(['vendor_id', 'total_amount', 'commission_total', 'net_vendor_amount']);

        $orderCount = $orders->count();
        $grossRevenue = (float) $orders->sum('total_amount');
        $commission = (float) $orders->sum('commission_total');
        $netToVendors = (float) $orders->sum('net_vendor_amount');
        $marginRate = $grossRevenue > 0 ? $commission / $grossRevenue : 0.0;

        $payouts = $this->payoutHealth($from, $ageingDays);
        $concentration = $this->concentration($orders);

        $findings = $this->findings([
            'margin_rate' => $marginRate,
            'margin_floor' => $marginFloor,
            'concentration' => $concentration,
            'concentration_threshold' => $concentrationThreshold,
            'payouts' => $payouts,
            'ageing_days' => $ageingDays,
            'order_count' => $orderCount,
        ]);

        return [
            'window_days' => $windowDays,
            'window_start' => $from->toDateString(),
            'orders' => $orderCount,
            'gross_revenue' => round($grossRevenue, 2),
            'commission' => round($commission, 2),
            'net_to_vendors' => round($netToVendors, 2),
            'average_order_value' => round($orderCount > 0 ? $grossRevenue / $orderCount : 0.0, 2),
            'margin_rate' => round($marginRate, 4),
            'payouts' => $payouts,
            'concentration' => $concentration,
            'findings' => $findings,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function payoutHealth(Carbon $from, int $ageingDays): array
    {
        $payouts = DB::table('vendor_payouts')
            ->where('created_at', '>=', $from)
            ->get(['amount', 'status', 'requested_at', 'processed_at', 'paid_at']);

        $pendingStatuses = ['requested', 'pending', 'approved', 'processing'];
        $paidStatuses = ['paid', 'processed', 'completed', 'settled'];

        $pending = $payouts->filter(fn ($payout) => in_array($payout->status, $pendingStatuses, true));
        $paid = $payouts->filter(fn ($payout) => in_array($payout->status, $paidStatuses, true) || $payout->paid_at);

        $overdueCutoff = Carbon::now()->subDays($ageingDays);
        $overdue = $pending->filter(function ($payout) use ($overdueCutoff) {
            $reference = $payout->requested_at ?: null;

            return $reference !== null && Carbon::parse($reference)->lessThan($overdueCutoff);
        });

        $ages = $paid->filter(fn ($payout) => $payout->requested_at && ($payout->paid_at || $payout->processed_at))
            ->map(function ($payout) {
                $end = Carbon::parse($payout->paid_at ?: $payout->processed_at);

                return (float) Carbon::parse($payout->requested_at)->diffInDays($end);
            });

        return [
            'requested_count' => $payouts->count(),
            'pending_count' => $pending->count(),
            'pending_amount' => round((float) $pending->sum('amount'), 2),
            'paid_count' => $paid->count(),
            'paid_amount' => round((float) $paid->sum('amount'), 2),
            'overdue_count' => $overdue->count(),
            'overdue_amount' => round((float) $overdue->sum('amount'), 2),
            'average_days_to_pay' => round($ages->count() > 0 ? $ages->avg() : 0.0, 2),
            'ageing_days' => $ageingDays,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $orders
     * @return array<string, mixed>
     */
    protected function concentration($orders): array
    {
        $byVendor = $orders->filter(fn ($order) => $order->vendor_id)
            ->groupBy(fn ($order) => (int) $order->vendor_id)
            ->map(fn ($group) => (float) $group->sum('total_amount'));

        $total = $byVendor->sum();

        if ($total <= 0 || $byVendor->isEmpty()) {
            return ['vendors' => $byVendor->count(), 'top_vendor_share' => 0.0, 'hhi' => 0.0, 'top_vendor_id' => null];
        }

        $shares = $byVendor->map(fn ($amount) => $amount / $total);
        $hhi = (float) round($shares->map(fn ($share) => $share * $share)->sum() * 10000, 2);
        $topVendorId = $byVendor->sortDesc()->keys()->first();

        return [
            'vendors' => $byVendor->count(),
            'top_vendor_share' => round((float) $shares->max(), 4),
            'hhi' => $hhi,
            'top_vendor_id' => $topVendorId,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<int, array{code: string, severity: string, message: string}>
     */
    protected function findings(array $input): array
    {
        $findings = [];

        if ($input['order_count'] === 0) {
            return [[
                'code' => 'no_activity',
                'severity' => 'info',
                'message' => 'No settled orders in the window, so margin and concentration are not meaningful yet.',
            ]];
        }

        if ($input['margin_floor'] > 0 && $input['margin_rate'] < $input['margin_floor']) {
            $findings[] = [
                'code' => 'margin_below_floor',
                'severity' => 'warning',
                'message' => sprintf(
                    'Platform margin %.2f%% is below the configured floor of %.2f%%.',
                    $input['margin_rate'] * 100,
                    $input['margin_floor'] * 100
                ),
            ];
        }

        if ($input['concentration_threshold'] > 0 && $input['concentration']['top_vendor_share'] > $input['concentration_threshold']) {
            $findings[] = [
                'code' => 'revenue_concentration',
                'severity' => 'warning',
                'message' => sprintf(
                    'Vendor #%s accounts for %.1f%% of window revenue, above the %.1f%% concentration limit.',
                    $input['concentration']['top_vendor_id'],
                    $input['concentration']['top_vendor_share'] * 100,
                    $input['concentration_threshold'] * 100
                ),
            ];
        }

        if ($input['payouts']['overdue_count'] > 0) {
            $findings[] = [
                'code' => 'payouts_overdue',
                'severity' => 'warning',
                'message' => sprintf(
                    '%d payout(s) worth %s have been pending for more than %d days.',
                    $input['payouts']['overdue_count'],
                    number_format((float) $input['payouts']['overdue_amount'], 2),
                    $input['ageing_days']
                ),
            ];
        }

        if (empty($findings)) {
            $findings[] = [
                'code' => 'healthy',
                'severity' => 'info',
                'message' => 'Margin, payout ageing and revenue concentration are all within configured limits.',
            ];
        }

        return $findings;
    }
}
