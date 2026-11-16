<?php

namespace App\Services\Sales;

use App\Models\Order;
use App\Models\SalesAttribution;
use App\Models\SalesLead;
use App\Models\SalesRep;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Sales attribution and commission reporting. Attribution links a placed order
 * to the salesperson who earned it; the commission is a straight percentage of
 * the order total. Overriding an existing attribution is governed by the
 * maker-checker approval flow (ability "sales.attribution.override").
 */
class SalesAttributionService
{
    public function commissionFor(SalesRep $rep, float $orderTotal): array
    {
        $rate = (float) $rep->commission_rate;
        $amount = round($orderTotal * ($rate / 100), 2);

        return ['rate' => $rate, 'amount' => $amount];
    }

    public function attribute(
        Order $order,
        SalesRep $rep,
        string $method = 'manual',
        ?int $actorId = null,
        ?string $notes = null,
    ): SalesAttribution {
        $orderTotal = (float) $order->total_amount;
        $commission = $this->commissionFor($rep, $orderTotal);

        return SalesAttribution::updateOrCreate(
            ['order_id' => $order->id],
            [
                'sales_rep_id' => $rep->id,
                'method' => $method,
                'commission_rate' => $commission['rate'],
                'order_total' => $orderTotal,
                'commission_amount' => $commission['amount'],
                'status' => 'pending',
                'notes' => $notes,
                'created_by' => $actorId,
                'approved_by' => null,
                'approved_at' => null,
            ]
        );
    }

    public function approve(SalesAttribution $attribution, int $actorId): SalesAttribution
    {
        $attribution->update([
            'status' => 'approved',
            'approved_by' => $actorId,
            'approved_at' => now(),
        ]);

        return $attribution;
    }

    public function reject(SalesAttribution $attribution, int $actorId): SalesAttribution
    {
        $attribution->update([
            'status' => 'rejected',
            'approved_by' => $actorId,
            'approved_at' => now(),
        ]);

        return $attribution;
    }

    /**
     * Per-representative performance within an optional date window.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function leaderboard(?Carbon $from = null, ?Carbon $to = null)
    {
        $attributions = SalesAttribution::query()
            ->active()
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->select('sales_rep_id', DB::raw('COUNT(*) as attributed_orders'), DB::raw('SUM(order_total) as attributed_total'), DB::raw('SUM(commission_amount) as commission_total'))
            ->groupBy('sales_rep_id')
            ->get()
            ->keyBy('sales_rep_id');

        $leads = SalesLead::query()
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->select('sales_rep_id', DB::raw('COUNT(*) as total'), DB::raw("SUM(CASE WHEN status = 'won' THEN 1 ELSE 0 END) as won"))
            ->groupBy('sales_rep_id')
            ->get()
            ->keyBy('sales_rep_id');

        return SalesRep::query()
            ->orderBy('name')
            ->get()
            ->map(function (SalesRep $rep) use ($attributions, $leads) {
                $attr = $attributions->get($rep->id);
                $lead = $leads->get($rep->id);
                $attributedTotal = (float) ($attr->attributed_total ?? 0);
                $target = (float) $rep->monthly_target;

                return [
                    'id' => $rep->id,
                    'name' => $rep->name,
                    'code' => $rep->code,
                    'is_active' => $rep->is_active,
                    'commission_rate' => (float) $rep->commission_rate,
                    'monthly_target' => $target,
                    'leads' => (int) ($lead->total ?? 0),
                    'leads_won' => (int) ($lead->won ?? 0),
                    'attributed_orders' => (int) ($attr->attributed_orders ?? 0),
                    'attributed_total' => $attributedTotal,
                    'commission_total' => (float) ($attr->commission_total ?? 0),
                    'target_attainment' => $target > 0 ? round(($attributedTotal / $target) * 100, 1) : null,
                ];
            })
            ->sortByDesc('attributed_total')
            ->values();
    }

    /**
     * Platform-wide attribution KPIs.
     *
     * @return array<string, float|int>
     */
    public function summary(?Carbon $from = null, ?Carbon $to = null): array
    {
        $query = SalesAttribution::query()
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to));

        return [
            'attributed_orders' => (clone $query)->count(),
            'attributed_total' => round((float) (clone $query)->sum('order_total'), 2),
            'commission_total' => round((float) (clone $query)->sum('commission_amount'), 2),
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'approved' => (clone $query)->where('status', 'approved')->count(),
            'reps' => SalesRep::count(),
            'active_reps' => SalesRep::active()->count(),
            'leads' => SalesLead::when($from, fn ($q) => $q->where('created_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('created_at', '<=', $to))->count(),
        ];
    }
}
