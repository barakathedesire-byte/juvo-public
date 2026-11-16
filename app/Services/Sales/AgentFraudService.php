<?php

namespace App\Services\Sales;

use App\Models\AgentCommissionEntry;
use App\Models\AgentFraudReview;
use App\Models\Order;
use App\Models\SalesAttribution;
use App\Models\SalesRep;
use App\Services\Admin\AuditLogger;
use Illuminate\Support\Carbon;

/**
 * Rule-based fraud/abuse detection for the agent network. Signals are recorded
 * as reviewable cases; high-severity cases hold commission eligibility (see
 * AgentCommissionEngine::markEligibleForOrder). Detection is deliberately
 * explainable — no opaque model decides money.
 */
class AgentFraudService
{
    public function __construct(
        protected AuditLogger $audit,
        protected AgentCommissionEngine $engine,
    ) {}

    /**
     * @return array<int, AgentFraudReview>
     */
    public function scanAgent(SalesRep $agent): array
    {
        $created = [];

        $orderIds = SalesAttribution::where('sales_rep_id', $agent->id)->pluck('order_id');
        $orders = Order::whereIn('id', $orderIds)->get();

        $selfPurchases = $orders->filter(fn (Order $o) => $this->engine->isSelfPurchase($o, $agent));

        if ($selfPurchases->isNotEmpty()) {
            $created[] = $this->review($agent, 'self_purchase', 'high', [
                'orders' => $selfPurchases->pluck('id')->all(),
                'count' => $selfPurchases->count(),
            ]);
        }

        $lost = $orders->whereIn('status', ['canceled', 'returned'])->count();
        $minOrders = (int) $this->thresholds()['min_orders_for_rate'];
        $rateThreshold = (float) $this->thresholds()['cancellation_rate_threshold'];

        if ($orders->count() >= $minOrders && ($lost / $orders->count()) > $rateThreshold) {
            $created[] = $this->review($agent, 'cancellation_rate', 'medium', [
                'canceled' => $lost,
                'total' => $orders->count(),
            ]);
        }

        $velocityLimit = (int) $this->thresholds()['velocity_orders_per_day'];
        $recent = $orders->filter(fn (Order $o) => $o->created_at && $o->created_at->greaterThan(Carbon::now()->subDay()))->count();
        if ($recent > $velocityLimit) {
            $created[] = $this->review($agent, 'velocity', 'medium', ['orders_last_24h' => $recent]);
        }

        return array_values(array_filter($created));
    }

    /**
     * Editable detection thresholds. Detection remains deterministic; the
     * configuration only tunes sensitivity.
     *
     * @return array<string, mixed>
     */
    protected function thresholds(): array
    {
        try {
            return array_merge([
                'min_orders_for_rate' => 5,
                'cancellation_rate_threshold' => 0.5,
                'velocity_orders_per_day' => 10,
                'auto_hold_severity' => 'high',
            ], app(\App\Services\Intelligence\AlgorithmRegistry::class)->configFor('agent_fraud_scoring'));
        } catch (\Throwable $e) {
            return [
                'min_orders_for_rate' => 5,
                'cancellation_rate_threshold' => 0.5,
                'velocity_orders_per_day' => 10,
                'auto_hold_severity' => 'high',
            ];
        }
    }

    public function scanOrder(Order $order): ?AgentFraudReview
    {
        $agent = $this->engine->resolveAgentForOrder($order);

        if (! $agent) {
            return null;
        }

        if ($this->engine->isSelfPurchase($order, $agent)) {
            return $this->review($agent, 'self_purchase', 'high', [
                'order_id' => $order->id,
            ], $order->id);
        }

        return null;
    }

    public function scanAll(): int
    {
        $count = 0;

        SalesRep::query()->whereIn('status', SalesRep::OPERATIONAL_STATUSES)->chunkById(100, function ($agents) use (&$count) {
            foreach ($agents as $agent) {
                $count += count($this->scanAgent($agent));
            }
        });

        return $count;
    }

    /**
     * @param  array<string, mixed>  $signals
     */
    public function review(SalesRep $agent, string $type, string $severity, array $signals = [], ?int $orderId = null): AgentFraudReview
    {
        $existing = AgentFraudReview::where('agent_id', $agent->id)
            ->where('type', $type)
            ->when($orderId, fn ($q) => $q->where('order_id', $orderId), fn ($q) => $q->whereNull('order_id'))
            ->open()
            ->first();

        if ($existing) {
            return $existing;
        }

        $review = AgentFraudReview::create([
            'agent_id' => $agent->id,
            'order_id' => $orderId,
            'type' => $type,
            'severity' => $severity,
            'score' => $this->score($severity),
            'signals' => $signals,
            'status' => 'open',
        ]);

        if (in_array($severity, ['high', 'critical'], true)) {
            AgentCommissionEntry::where('agent_id', $agent->id)
                ->when($orderId, fn ($q) => $q->where('order_id', $orderId))
                ->whereIn('state', ['pending', 'provisional'])
                ->get()
                ->each(fn (AgentCommissionEntry $e) => $this->engine->hold($e, 'Fraud review: '.$type));
        }

        $this->audit->log('agent.fraud.flagged', $review, [], [
            'agent_id' => $agent->id,
            'type' => $type,
            'severity' => $severity,
        ]);

        return $review;
    }

    public function resolve(AgentFraudReview $review, int $actorId, string $decision, ?string $reason = null): AgentFraudReview
    {
        $review->update([
            'status' => $decision === 'dismiss' ? 'dismissed' : 'resolved',
            'decision' => $decision,
            'resolution_reason' => $reason,
            'resolved_by' => $actorId,
            'resolved_at' => now(),
        ]);

        if (in_array($decision, ['clear', 'dismiss'], true)) {
            AgentCommissionEntry::where('agent_id', $review->agent_id)
                ->when($review->order_id, fn ($q) => $q->where('order_id', $review->order_id))
                ->where('state', 'held')
                ->get()
                ->each(fn (AgentCommissionEntry $e) => $this->engine->release($e));
        } else {
            AgentCommissionEntry::where('agent_id', $review->agent_id)
                ->when($review->order_id, fn ($q) => $q->where('order_id', $review->order_id))
                ->whereNull('reversal_of_id')
                ->whereNotIn('state', ['reversed', 'paid'])
                ->get()
                ->each(fn (AgentCommissionEntry $e) => $this->engine->reverseEntry($e, 'Fraud confirmed: '.($reason ?? $review->type)));
        }

        $this->audit->log('agent.fraud.resolved', $review, [], [
            'decision' => $decision,
        ], $reason, \App\Models\User::find($actorId));

        return $review;
    }

    protected function score(string $severity): int
    {
        return match ($severity) {
            'critical' => 90,
            'high' => 70,
            'medium' => 45,
            default => 20,
        };
    }
}
