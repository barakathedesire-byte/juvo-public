<?php

namespace App\Services\Sales;

use App\Models\Order;
use App\Models\SalesAttribution;
use App\Models\SalesRep;
use App\Services\Admin\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Server-side attribution of orders to sales agents.
 *
 * Attribution is NEVER taken from client input: the referral code is only ever
 * read back from the signed server session or supplied by an authenticated
 * agent placing an assisted order. This prevents a caller from claiming an
 * arbitrary order for an arbitrary agent.
 */
class AgentAttributionService
{
    public function __construct(
        protected AgentCommissionEngine $engine,
        protected AuditLogger $audit,
    ) {}

    /**
     * Attribute an order to an agent and immediately post commission entries.
     *
     * @param  string|null  $referralCode  server-trusted referral code (session)
     * @param  SalesRep|null  $agent  authenticated agent for assisted ordering
     */
    public function attributeFromCheckout(Order $order, ?string $referralCode = null, ?SalesRep $agent = null): ?SalesAttribution
    {
        $agent = $agent ?: SalesRep::forCode($referralCode);

        if (! $agent || ! $agent->canOperate()) {
            return null;
        }

        return $this->attribute($order, $agent, $agent === SalesRep::forCode($referralCode) ? 'referral' : 'assisted');
    }

    public function attribute(Order $order, SalesRep $agent, string $source = 'agent'): SalesAttribution
    {
        return DB::transaction(function () use ($order, $agent, $source) {
            $policy = app(CommissionPolicyService::class)->activePolicy();

            $attribution = SalesAttribution::updateOrCreate(
                ['order_id' => $order->id],
                [
                    'sales_rep_id' => $agent->id,
                    'method' => $source,
                    'source' => $source,
                    'commission_rate' => $policy?->rate ?? $agent->commission_rate ?? 0,
                    'policy_id' => $policy?->id,
                    'policy_version' => $policy?->version,
                    'commission_basis' => $policy?->basis,
                    'order_total' => (float) $order->total_amount,
                    'status' => 'pending',
                    'attributed_at' => now(),
                    'agent_confirmed' => false,
                    'confirmation_deadline' => $agent->customer_confirmation_required ? now()->addDays(7) : null,
                ]
            );

            $entries = $this->engine->recordForOrder($order, $agent, $policy);
            $direct = collect($entries)->firstWhere('role', 'direct');

            if ($direct) {
                $attribution->update([
                    'commission_rate' => $direct->rate,
                    'commission_amount' => $direct->amount,
                    'commission_basis' => $direct->basis,
                ]);
            }

            $this->audit->log('agent.attribution.created', $attribution, [], [
                'order_id' => $order->id,
                'sales_rep_id' => $agent->id,
                'source' => $source,
            ]);

            return $attribution;
        });
    }

    public function confirm(SalesAttribution $attribution, string $method = 'self'): SalesAttribution
    {
        $attribution->update([
            'agent_confirmed' => true,
            'confirmation_method' => $method,
            'confirmed_at' => now(),
            'status' => 'approved',
        ]);

        return $attribution;
    }

    public function dispute(SalesAttribution $attribution, string $reason, ?int $actorId = null): SalesAttribution
    {
        $before = ['dispute_status' => $attribution->dispute_status];

        $attribution->update(['dispute_status' => 'open']);

        $this->audit->log('agent.attribution.disputed', $attribution, $before, [
            'dispute_status' => 'open',
        ], $reason);

        return $attribution;
    }

    public function forOrder(Order $order): ?SalesAttribution
    {
        return SalesAttribution::where('order_id', $order->id)->first();
    }
}
