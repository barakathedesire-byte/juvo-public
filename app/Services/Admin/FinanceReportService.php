<?php

namespace App\Services\Admin;

use App\Models\Refund;
use App\Models\Vendor;
use App\Models\VendorCommission;
use App\Models\VendorPayout;
use App\Models\VendorTransaction;
use App\Services\Vendor\VendorFinanceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Aggregates the platform's financial picture for the admin Finance Command
 * Center: platform revenue, vendor earnings, payouts, refunds, adjustments and
 * a per-vendor reconciliation.
 */
class FinanceReportService
{
    public function __construct(protected VendorFinanceService $finance) {}

    /**
     * @return array<string, float|int>
     */
    public function kpis(?Carbon $from = null, ?Carbon $to = null): array
    {
        $commissions = VendorCommission::query();
        if ($from && $to) {
            $commissions->whereBetween('created_at', [$from, $to]);
        }

        $row = (clone $commissions)
            ->selectRaw("
                SUM(CASE WHEN status != 'reversed' THEN gross_amount ELSE 0 END) AS gross,
                SUM(CASE WHEN status != 'reversed' THEN commission_amount ELSE 0 END) AS revenue,
                SUM(CASE WHEN status != 'reversed' THEN net_amount ELSE 0 END) AS vendor_net,
                SUM(CASE WHEN status = 'available' THEN net_amount ELSE 0 END) AS payable,
                SUM(CASE WHEN status = 'pending' THEN net_amount ELSE 0 END) AS pending,
                SUM(CASE WHEN status = 'settled' THEN net_amount ELSE 0 END) AS settled,
                SUM(CASE WHEN status = 'reversed' THEN net_amount ELSE 0 END) AS reversed
            ")
            ->first();

        $payouts = VendorPayout::query();
        $refunds = Refund::query();
        $adjustments = VendorTransaction::query()->where('type', 'adjustment');
        if ($from && $to) {
            $payouts->whereBetween('created_at', [$from, $to]);
            $refunds->whereBetween('created_at', [$from, $to]);
            $adjustments->whereBetween('created_at', [$from, $to]);
        }

        return [
            'gross' => round((float) ($row->gross ?? 0), 2),
            'revenue' => round((float) ($row->revenue ?? 0), 2),
            'vendor_net' => round((float) ($row->vendor_net ?? 0), 2),
            'payable' => round((float) ($row->payable ?? 0), 2),
            'pending_earnings' => round((float) ($row->pending ?? 0), 2),
            'settled' => round((float) ($row->settled ?? 0), 2),
            'reversed' => round((float) ($row->reversed ?? 0), 2),
            'adjustments' => round((float) $adjustments->sum('amount'), 2),
            'paid_out' => round((float) (clone $payouts)->where('status', 'paid')->sum('amount'), 2),
            'pending_payout_count' => (int) (clone $payouts)->whereIn('status', ['requested', 'approved', 'processing'])->count(),
            'pending_payout_amount' => round((float) (clone $payouts)->whereIn('status', ['requested', 'approved', 'processing'])->sum('amount'), 2),
            'open_refund_count' => (int) (clone $refunds)->whereIn('status', ['requested', 'approved'])->count(),
            'open_refund_amount' => round((float) (clone $refunds)->whereIn('status', ['requested', 'approved'])->sum('amount'), 2),
        ];
    }

    /**
     * Per-vendor reconciliation of earnings against payouts.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function reconciliation(?string $search = null): Collection
    {
        $vendors = Vendor::query()
            ->when($from = $from ?? null, fn ($q) => $q)
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
            ->orderBy('name')
            ->get();

        return $vendors->map(function (Vendor $vendor) {
            $balances = $this->finance->balances($vendor);

            return [
                'vendor' => $vendor,
                'gross' => $balances['gross'],
                'commission' => $balances['commission'],
                'net' => round($balances['gross'] - $balances['commission'], 2),
                'pending' => $balances['pending'],
                'available' => $balances['available'],
                'reserved' => $balances['reserved'],
                'withdrawable' => $balances['withdrawable'],
                'paid' => $balances['paid'],
                'reversed' => $balances['reversed'],
                'adjustments' => $balances['adjustments'],
                'currency' => $balances['currency'],
            ];
        });
    }
}
