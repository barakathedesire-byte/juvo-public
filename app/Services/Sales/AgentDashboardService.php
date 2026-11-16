<?php

namespace App\Services\Sales;

use App\Models\AgentCommissionEntry;
use App\Models\AgentFraudReview;
use App\Models\AgentPayoutRequest;
use App\Models\AgentRankDefinition;
use App\Models\Order;
use App\Models\SalesRep;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Read models for the agent portal and the admin network dashboard. All agent
 * figures are computed from the immutable ledger, never from cached totals, so
 * an agent always sees exactly the balance they can be paid.
 */
class AgentDashboardService
{
    public function __construct(
        protected AgentCommissionEngine $engine,
        protected AgentRankService $ranks,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(SalesRep $agent): array
    {
        $balance = $this->engine->balanceFor($agent);
        $stats = $this->ranks->stats($agent);
        $rank = $agent->currentRank();

        $nextRank = AgentRankDefinition::active()
            ->where('level', '>', $rank->level ?? -1)
            ->orderBy('level')
            ->first();

        return [
            'agent' => $agent,
            'balance' => $balance,
            'rank' => $rank,
            'next_rank' => $nextRank,
            'rank_progress' => $this->rankProgress($stats['volume'], $nextRank),
            'stats' => $stats,
            'leads_total' => $agent->leads()->count(),
            'leads_won' => $agent->leads()->where('status', 'won')->count(),
            'attributed_orders' => $agent->attributions()->count(),
            'recent_entries' => $agent->commissionEntries()->latest('id')->limit(10)->get(),
            'recent_payouts' => $agent->payoutRequests()->latest('id')->limit(5)->get(),
            'open_fraud' => $agent->fraudReviews()->open()->count(),
        ];
    }

    protected function rankProgress(float $volume, ?AgentRankDefinition $next): ?float
    {
        if (! $next || (float) $next->min_sales_volume <= 0) {
            return null;
        }

        return round(min(100, ($volume / (float) $next->min_sales_volume) * 100), 1);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function leaderboard(int $limit = 10, ?Carbon $from = null, ?Carbon $to = null)
    {
        $rows = Order::query()
            ->join('sales_attributions', 'sales_attributions.order_id', '=', 'orders.id')
            ->whereIn('orders.status', ['delivered', 'completed'])
            ->when($from, fn ($q) => $q->where('orders.created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('orders.created_at', '<=', $to))
            ->groupBy('sales_attributions.sales_rep_id')
            ->selectRaw('sales_attributions.sales_rep_id as agent_id, COUNT(*) as orders, SUM(orders.total_amount) as volume')
            ->orderByDesc('volume')
            ->limit($limit)
            ->get();

        $names = SalesRep::whereIn('id', $rows->pluck('agent_id')->filter())->pluck('name', 'id');

        return $rows->map(fn ($row) => [
            'agent_id' => $row->agent_id,
            'name' => $names[$row->agent_id] ?? '—',
            'orders' => (int) $row->orders,
            'volume' => round((float) $row->volume, 2),
        ])->values();
    }

    /**
     * @return array<string, float|int>
     */
    public function networkSummary(): array
    {
        $payoutPending = AgentPayoutRequest::open();

        return [
            'agents' => SalesRep::count(),
            'active' => SalesRep::whereIn('status', SalesRep::OPERATIONAL_STATUSES)->count(),
            'pending' => SalesRep::whereIn('status', ['draft', 'applied', 'verification', 'review'])->count(),
            'suspended' => SalesRep::where('status', 'suspended')->count(),
            'commission_pending' => round((float) AgentCommissionEntry::whereIn('state', ['pending', 'provisional'])->sum('amount'), 2),
            'commission_available' => round((float) AgentCommissionEntry::whereIn('state', ['eligible', 'approved'])->sum('amount'), 2),
            'commission_paid' => round((float) AgentCommissionEntry::where('state', 'paid')->sum('amount'), 2),
            'commission_reversed' => round((float) AgentCommissionEntry::where('state', 'reversed')->sum('amount'), 2),
            'payout_pending_count' => (clone $payoutPending)->count(),
            'payout_pending_amount' => round((float) (clone $payoutPending)->sum('amount'), 2),
            'fraud_open' => AgentFraudReview::open()->count(),
        ];
    }
}
