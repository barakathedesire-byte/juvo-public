<?php

namespace App\Services\Sales;

use App\Models\AgentCommissionEntry;
use App\Models\AgentCommissionPolicy;
use App\Models\AgentFraudReview;
use App\Models\Order;
use App\Models\SalesAttribution;
use App\Models\SalesRep;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * The authoritative, deterministic agent-commission ledger.
 *
 * Design rules enforced here:
 *  - commission entries are IMMUTABLE; corrections are separate reversal rows;
 *  - every posting is idempotent (a stable dedup_key guards against replays);
 *  - arithmetic is integer-minor-unit safe (see Support\Money);
 *  - a commission never becomes payable before the order is delivered AND the
 *    cash is collected (COD is not settled on dispatch);
 *  - the live balance is the sum of entries in an "open" state, so reversals
 *    (state = reversed) fall out of the balance automatically.
 */
class AgentCommissionEngine
{
    /** States that a positive entry can still be paid from. */
    public const OPEN_STATES = ['eligible', 'approved', 'payable'];

    public function __construct(
        protected CommissionPolicyService $policies,
    ) {}

    /**
     * Post the direct (and, when enabled, team) commission entries for an order.
     *
     * @return array<int, AgentCommissionEntry>
     */
    public function recordForOrder(Order $order, ?SalesRep $agent = null, ?AgentCommissionPolicy $policy = null): array
    {
        $agent = $agent ?: $this->resolveAgentForOrder($order);

        if (! $agent) {
            return [];
        }

        $policy = $policy ?: $this->policies->activePolicy();

        if (! $policy) {
            return [];
        }

        $entries = [];

        $direct = $this->recordDirect($order, $agent, $policy);
        if ($direct) {
            $entries[] = $direct;
        }

        if ($policy->team_enabled) {
            foreach ($this->recordTeam($order, $agent, $policy, $this->basisFor($order, $policy)) as $teamEntry) {
                $entries[] = $teamEntry;
            }
        }

        return $entries;
    }

    public function recordDirect(Order $order, SalesRep $agent, AgentCommissionPolicy $policy): ?AgentCommissionEntry
    {
        if (! $policy->allow_guest_orders && $order->is_guest) {
            return null;
        }

        if (! $policy->allow_self_purchase && $this->isSelfPurchase($order, $agent)) {
            return null;
        }

        $basis = $this->basisFor($order, $policy);

        return $this->post($order, $agent, $policy, 'direct', $basis, (float) $policy->rate, null);
    }

    /**
     * @return array<int, AgentCommissionEntry>
     */
    protected function recordTeam(Order $order, SalesRep $agent, AgentCommissionPolicy $policy, float $basis): array
    {
        $entries = [];
        $sponsor = $agent->sponsor;
        $depth = 1;
        $cumulativeRate = 0.0;
        $maxTotal = (float) $policy->team_max_total_rate;

        while ($sponsor && $depth <= (int) $policy->team_max_depth) {
            if ($sponsor->canOperate()) {
                $rate = (float) $policy->team_rate;

                if ($maxTotal > 0) {
                    $rate = max(0.0, min($rate, $maxTotal - $cumulativeRate));
                }

                if ($rate > 0) {
                    $entry = $this->post($order, $sponsor, $policy, 'team', $basis, $rate, $depth);
                    if ($entry) {
                        $entries[] = $entry;
                        $cumulativeRate += $rate;
                    }
                }
            }

            $sponsor = $sponsor->sponsor;
            $depth++;
        }

        return $entries;
    }

    /**
     * Create (idempotently) a single commission posting.
     */
    protected function post(
        Order $order,
        SalesRep $agent,
        AgentCommissionPolicy $policy,
        string $role,
        float $basis,
        float $rate,
        ?int $depth,
    ): ?AgentCommissionEntry {
        $basis = max(0.0, round($basis, 2));

        if ($policy->min_order_value !== null && $basis < (float) $policy->min_order_value) {
            return null;
        }

        $amount = Money::of($basis)->percent($rate)->add(Money::of($policy->fixed_amount))->toFloat();

        if ($policy->max_commission !== null) {
            $amount = min($amount, (float) $policy->max_commission);
        }

        $amount = round($amount, 2);

        if ($amount <= 0) {
            return null;
        }

        $suffix = $role === 'team' ? ':team:'.($depth ?? 1) : ':role:'.$role;
        $dedup = "order:{$order->id}:agent:{$agent->id}{$suffix}";

        return AgentCommissionEntry::firstOrCreate(
            ['dedup_key' => $dedup],
            [
                'agent_id' => $agent->id,
                'order_id' => $order->id,
                'policy_id' => $policy->id,
                'policy_version' => $policy->version,
                'role' => $role,
                'basis' => $policy->basis,
                'basis_amount' => $basis,
                'rate' => $rate,
                'amount' => $amount,
                'currency' => $order->currency ?: $agent->currency,
                'state' => 'pending',
            ]
        );
    }

    /**
     * The commissionable amount for an order under a policy.
     */
    public function basisFor(Order $order, AgentCommissionPolicy $policy): float
    {
        $eligible = (array) ($policy->eligible_category_ids ?? []);
        $excluded = (array) ($policy->excluded_category_ids ?? []);

        if ($eligible || $excluded) {
            $sum = 0.0;

            foreach ($order->details()->with('product')->get() as $detail) {
                $categoryId = $detail->product->category_id ?? null;

                if ($eligible && ! in_array($categoryId, $eligible, true)) {
                    continue;
                }

                if ($excluded && in_array($categoryId, $excluded, true)) {
                    continue;
                }

                $sum += ((float) $detail->price * (int) $detail->quantity) - (float) $detail->discount_amount;
            }

            return round(max(0.0, $sum), 2);
        }

        $base = match ($policy->basis) {
            'order_total' => (float) $order->total_amount,
            'subtotal' => (float) $order->subtotal,
            default => (float) $order->subtotal - (float) $order->discount_amount,
        };

        if ($policy->basis !== 'order_total') {
            if ($policy->include_tax) {
                $base += (float) $order->tax_amount;
            }
            if ($policy->include_delivery_fee) {
                $base += (float) $order->delivery_fee;
            }
        }

        return round(max(0.0, $base), 2);
    }

    public function isSelfPurchase(Order $order, SalesRep $agent): bool
    {
        $orderEmails = array_filter(array_map('strtolower', array_filter([
            $order->guest_email,
            optional($order->customer)->email,
        ])));

        $agentEmails = array_filter(array_map('strtolower', array_filter([
            $agent->email,
            optional($agent->user)->email,
        ])));

        return (bool) array_intersect($orderEmails, $agentEmails);
    }

    /**
     * Promote an order's pending commissions to "eligible" once it is both
     * delivered/completed and fully paid, unless a fraud hold applies.
     */
    public function markEligibleForOrder(Order $order): int
    {
        if (! in_array($order->status, ['delivered', 'completed'], true) || $order->payment_status !== 'paid') {
            return 0;
        }

        $heldAgentIds = AgentFraudReview::query()
            ->where('order_id', $order->id)
            ->open()
            ->whereIn('severity', ['high', 'critical'])
            ->pluck('agent_id')
            ->all();

        $query = AgentCommissionEntry::where('order_id', $order->id)
            ->whereIn('state', ['pending', 'provisional']);

        if ($heldAgentIds) {
            $query->whereNotIn('agent_id', $heldAgentIds);
        }

        return $query->update(['state' => 'eligible', 'eligible_at' => now()]);
    }

    /**
     * Reverse every non-final commission posting for an order. Corrections are
     * recorded as explicit reversal rows; a settled (paid) entry becomes a
     * negative clawback adjustment instead of being mutated.
     */
    public function reverseForOrder(Order $order, ?string $reason = null): int
    {
        $count = 0;

        foreach (AgentCommissionEntry::where('order_id', $order->id)
            ->whereNull('reversal_of_id')
            ->whereNotIn('state', ['reversed'])
            ->get() as $entry) {
            if ($this->reverseEntry($entry, $reason)) {
                $count++;
            }
        }

        return $count;
    }

    public function reverseEntry(AgentCommissionEntry $entry, ?string $reason = null): ?AgentCommissionEntry
    {
        if ($entry->state === 'reversed') {
            return null;
        }

        if ($entry->state === 'payable') {
            throw new RuntimeException('Cannot reverse a commission that is reserved in an open payout. Reject the payout first.');
        }

        return DB::transaction(function () use ($entry, $reason) {
            if ($entry->state === 'paid') {
                return AgentCommissionEntry::firstOrCreate(
                    ['dedup_key' => 'rev:'.$entry->id],
                    [
                        'agent_id' => $entry->agent_id,
                        'order_id' => $entry->order_id,
                        'policy_id' => $entry->policy_id,
                        'policy_version' => $entry->policy_version,
                        'role' => 'adjustment',
                        'basis' => $entry->basis,
                        'basis_amount' => $entry->basis_amount,
                        'rate' => $entry->rate,
                        'amount' => -1 * (float) $entry->amount,
                        'currency' => $entry->currency,
                        'state' => 'eligible',
                        'reversal_of_id' => $entry->id,
                        'notes' => $reason,
                    ]
                );
            }

            $entry->update([
                'state' => 'reversed',
                'reversed_at' => now(),
                'hold_reason' => $reason,
            ]);

            return AgentCommissionEntry::firstOrCreate(
                ['dedup_key' => 'rev:'.$entry->id],
                [
                    'agent_id' => $entry->agent_id,
                    'order_id' => $entry->order_id,
                    'policy_id' => $entry->policy_id,
                    'policy_version' => $entry->policy_version,
                    'role' => $entry->role,
                    'basis' => $entry->basis,
                    'basis_amount' => $entry->basis_amount,
                    'rate' => $entry->rate,
                    'amount' => -1 * (float) $entry->amount,
                    'currency' => $entry->currency,
                    'state' => 'reversed',
                    'reversal_of_id' => $entry->id,
                    'notes' => $reason,
                ]
            );
        });
    }

    public function hold(AgentCommissionEntry $entry, string $reason): AgentCommissionEntry
    {
        $entry->update(['state' => 'held', 'hold_reason' => $reason]);

        return $entry;
    }

    public function release(AgentCommissionEntry $entry): AgentCommissionEntry
    {
        if ($entry->state !== 'held') {
            return $entry;
        }

        $entry->update([
            'state' => $entry->order && $this->orderIsEligible($entry->order) ? 'eligible' : 'pending',
            'hold_reason' => null,
        ]);

        return $entry;
    }

    protected function orderIsEligible(Order $order): bool
    {
        return in_array($order->status, ['delivered', 'completed'], true) && $order->payment_status === 'paid';
    }

    /**
     * Live balance components for an agent, derived from entry states.
     *
     * @return array{available: float, pending: float, held: float, paid: float, reversed: float}
     */
    public function balanceFor(SalesRep $agent): array
    {
        $sums = AgentCommissionEntry::query()
            ->where('agent_id', $agent->id)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN state IN ('eligible','approved') THEN amount ELSE 0 END), 0) as available,
                COALESCE(SUM(CASE WHEN state IN ('pending','provisional') THEN amount ELSE 0 END), 0) as pending,
                COALESCE(SUM(CASE WHEN state = 'held' THEN amount ELSE 0 END), 0) as held,
                COALESCE(SUM(CASE WHEN state = 'payable' THEN amount ELSE 0 END), 0) as reserved,
                COALESCE(SUM(CASE WHEN state = 'paid' THEN amount ELSE 0 END), 0) as paid,
                COALESCE(SUM(CASE WHEN state = 'reversed' THEN amount ELSE 0 END), 0) as reversed
            ")
            ->first();

        return [
            'available' => round((float) $sums->available, 2),
            'pending' => round((float) $sums->pending, 2),
            'held' => round((float) $sums->held, 2),
            'reserved' => round((float) $sums->reserved, 2),
            'paid' => round((float) $sums->paid, 2),
            'reversed' => round((float) $sums->reversed, 2),
        ];
    }

    public function resolveAgentForOrder(Order $order): ?SalesRep
    {
        $attribution = SalesAttribution::where('order_id', $order->id)->first();

        return $attribution?->rep;
    }

    /**
     * Safety net used by scheduled reconciliation: pick up any attributed order
     * that has no commission entries yet and post them.
     */
    public function reconcileMissing(): int
    {
        $posted = 0;

        $orders = Order::query()
            ->whereIn('id', SalesAttribution::query()->select('order_id'))
            ->whereNotIn('id', AgentCommissionEntry::query()->select('order_id')->whereNotNull('order_id'))
            ->get();

        foreach ($orders as $order) {
            try {
                foreach ($this->recordForOrder($order) as $_) {
                    $posted++;
                }
            } catch (\Throwable $e) {
                Log::warning('Agent commission reconcile failed for order #'.$order->id.': '.$e->getMessage());
            }
        }

        return $posted;
    }
}
