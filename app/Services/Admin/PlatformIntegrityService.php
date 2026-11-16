<?php

namespace App\Services\Admin;

use App\Models\ApprovalRequest;
use App\Models\Feature;
use App\Models\SalesAttribution;
use App\Models\SupportTicket;
use App\Models\Vendor;
use App\Models\VendorCommission;
use App\Models\VendorPayout;
use App\Services\Vendor\VendorFinanceService;
use Illuminate\Support\Facades\DB;

/**
 * Read-only integrity checks for the platform. Every monetary figure is derived
 * from immutable rows, so these checks assert that the derived values still
 * agree with the underlying records and that no operational record is stuck in
 * an impossible state. Used by the admin Integrity screen and the
 * `platform:reconcile` command (CI-safe with --strict).
 */
class PlatformIntegrityService
{
    public function __construct(protected VendorFinanceService $finance) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function checks(): array
    {
        return [
            $this->orderTotals(),
            $this->commissionMath(),
            $this->payoutOverdraw(),
            $this->supportSla(),
            $this->attributionMath(),
            $this->stuckApprovals(),
            $this->featureRegistry(),
        ];
    }

    /**
     * @return array{ok:int, warn:int, fail:int, total:int}
     */
    public function summary(): array
    {
        $summary = ['ok' => 0, 'warn' => 0, 'fail' => 0, 'total' => 0];

        foreach ($this->checks() as $check) {
            $summary[$check['status']]++;
            $summary['total']++;
        }

        return $summary;
    }

    protected function result(string $key, string $label, int $count, string $detail, string $severity = 'warn'): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'count' => $count,
            'status' => $count === 0 ? 'ok' : $severity,
            'detail' => $count === 0 ? 'OK' : $detail,
        ];
    }

    protected function orderTotals(): array
    {
        $count = (int) DB::table('orders')
            ->whereRaw('ABS(COALESCE(total_amount,0) - (COALESCE(subtotal,0) - COALESCE(discount_amount,0) + COALESCE(tax_amount,0) + COALESCE(delivery_fee,0))) > 0.01')
            ->where(function ($q) {
                // Only audit orders that actually recorded a line-item
                // breakdown; legacy aggregate-only orders have no components.
                $q->where('subtotal', '<>', 0)
                    ->orWhere('tax_amount', '<>', 0)
                    ->orWhere('discount_amount', '<>', 0)
                    ->orWhere('delivery_fee', '<>', 0);
            })
            ->count();

        return $this->result('order_totals', 'Order totals reconcile with line items', $count,
            'Orders whose total does not equal subtotal − discount + tax + delivery.', 'fail');
    }

    protected function commissionMath(): array
    {
        $count = (int) VendorCommission::query()
            ->whereRaw('ABS((COALESCE(gross_amount,0) - COALESCE(commission_amount,0)) - COALESCE(net_amount,0)) > 0.01')
            ->orWhereRaw('ABS(COALESCE(platform_fee,0) - COALESCE(commission_amount,0)) > 0.01')
            ->count();

        return $this->result('commission_math', 'Commission rows net = gross − commission', $count,
            'Commission rows where net, platform fee or commission do not balance.', 'fail');
    }

    protected function payoutOverdraw(): array
    {
        $vendorIds = VendorPayout::query()
            ->whereIn('status', ['requested', 'approved', 'processing'])
            ->distinct()
            ->pluck('vendor_id');

        $overdrawn = 0;

        foreach ($vendorIds as $vendorId) {
            $vendor = Vendor::find($vendorId);

            if (! $vendor) {
                continue;
            }

            $balances = $this->finance->balances($vendor);

            if (round($balances['available'] - $balances['reserved'], 2) < -0.01) {
                $overdrawn++;
            }
        }

        return $this->result('payout_overdraw', 'In-flight payouts do not exceed available balance', $overdrawn,
            'Vendors with in-flight payouts larger than their available balance.', 'fail');
    }

    protected function supportSla(): array
    {
        $count = (int) SupportTicket::query()
            ->whereNotIn('status', ['closed'])
            ->where(function ($q) {
                $q->whereNull('first_response_due_at')->orWhereNull('resolution_due_at');
            })
            ->count();

        return $this->result('support_sla', 'Open tickets carry SLA deadlines', $count,
            'Open tickets missing a first-response or resolution deadline.', 'warn');
    }

    protected function attributionMath(): array
    {
        $count = (int) SalesAttribution::query()
            ->whereRaw('ABS(COALESCE(commission_amount,0) - ROUND(COALESCE(order_total,0) * COALESCE(commission_rate,0) / 100, 2)) > 0.01')
            ->count();

        return $this->result('attribution_math', 'Attribution commission matches rate × order total', $count,
            'Attributions whose stored commission disagrees with the applied rate.', 'fail');
    }

    protected function stuckApprovals(): array
    {
        $count = (int) ApprovalRequest::query()
            ->open()
            ->where('created_at', '<', now()->subDays(7))
            ->count();

        return $this->result('stuck_approvals', 'No approval request is older than 7 days', $count,
            'Approval requests waiting for a decision for more than 7 days.', 'warn');
    }

    protected function featureRegistry(): array
    {
        $count = (int) Feature::query()
            ->where('rollout_percentage', '<', 0)
            ->orWhere('rollout_percentage', '>', 100)
            ->count();

        return $this->result('feature_registry', 'Feature rollout percentages are within 0–100', $count,
            'Feature flags configured with an out-of-range rollout percentage.', 'warn');
    }
}
