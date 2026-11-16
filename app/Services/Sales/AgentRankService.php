<?php

namespace App\Services\Sales;

use App\Models\AgentRankDefinition;
use App\Models\AgentRankHistory;
use App\Models\Order;
use App\Models\SalesAttribution;
use App\Models\SalesRep;
use Illuminate\Support\Facades\DB;

/**
 * Evaluates agents against the configured rank ladder using their delivered
 * sales history. Ranks are advisory recognitions and never grant money: the
 * bonus_rate only feeds the (separately governed) commission engine.
 */
class AgentRankService
{
    /**
     * @return array{volume: float, delivered: int, quality: ?float}
     */
    public function stats(SalesRep $agent, ?string $period = null): array
    {
        [$from, $to] = $this->periodBounds($period);

        $orderIds = SalesAttribution::where('sales_rep_id', $agent->id)->pluck('order_id');

        $base = Order::query()
            ->whereIn('id', $orderIds)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to));

        $delivered = (clone $base)->whereIn('status', ['delivered', 'completed'])->count();
        $volume = (float) (clone $base)->whereIn('status', ['delivered', 'completed'])->sum('total_amount');
        $lost = (clone $base)->whereIn('status', ['canceled', 'returned'])->count();

        $denominator = $delivered + $lost;
        $quality = $denominator > 0 ? round(($delivered / $denominator) * 100, 2) : null;

        return ['volume' => round($volume, 2), 'delivered' => $delivered, 'quality' => $quality];
    }

    public function evaluate(SalesRep $agent, ?string $period = null): ?AgentRankHistory
    {
        $stats = $this->stats($agent, $period);

        $rank = AgentRankDefinition::active()
            ->orderByDesc('level')
            ->get()
            ->first(fn (AgentRankDefinition $r) => $r->qualifies($stats['volume'], $stats['delivered'], $stats['quality']));

        $previous = $agent->rankHistories()->first();

        return AgentRankHistory::create([
            'agent_id' => $agent->id,
            'rank_definition_id' => $rank?->id,
            'previous_rank_id' => $previous?->rank_definition_id,
            'period' => $period ?: now()->format('Y-m'),
            'sales_volume' => $stats['volume'],
            'delivered_orders' => $stats['delivered'],
            'quality_score' => $stats['quality'],
            'promoted' => $rank && $previous && ($rank->level > ($previous->rank?->level ?? 0)),
            'reason' => $rank ? 'Evaluated against rank ladder' : 'No qualifying rank',
            'calculated_at' => now(),
        ]);
    }

    public function evaluateAll(?string $period = null): int
    {
        $count = 0;

        SalesRep::query()->whereIn('status', SalesRep::OPERATIONAL_STATUSES)->chunkById(100, function ($agents) use ($period, &$count) {
            foreach ($agents as $agent) {
                $this->evaluate($agent, $period);
                $count++;
            }
        });

        return $count;
    }

    /**
     * @return array{0: ?\Illuminate\Support\Carbon, 1: ?\Illuminate\Support\Carbon}
     */
    protected function periodBounds(?string $period): array
    {
        if (! $period || ! preg_match('/^\d{4}-\d{2}$/', $period)) {
            return [null, null];
        }

        $start = \Illuminate\Support\Carbon::createFromFormat('Y-m', $period)->startOfMonth();

        return [$start, $start->copy()->endOfMonth()];
    }
}
