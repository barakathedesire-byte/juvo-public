<?php

namespace Tests\Concerns;

use App\Models\AgentCommissionPolicy;
use App\Models\Order;
use App\Models\SalesAttribution;
use App\Models\SalesRep;
use App\Services\Sales\AgentAttributionService;
use App\Services\Sales\CommissionPolicyService;

trait BuildsAgentNetwork
{
    protected function resetAgentPolicies(): void
    {
        AgentCommissionPolicy::query()->update(['is_active' => false]);
    }

    protected function agentPolicy(array $attrs = []): AgentCommissionPolicy
    {
        return app(CommissionPolicyService::class)->create(array_merge([
            'name' => 'Policy '.uniqid(),
            'policy_key' => 'policy-'.uniqid(),
            'rate' => 5,
            'basis' => 'order_total',
            'is_active' => true,
            'is_default' => false,
        ], $attrs));
    }

    protected function agent(array $attrs = []): SalesRep
    {
        return SalesRep::create(array_merge([
            'code' => 'AG'.strtoupper(uniqid()),
            'name' => 'Agent '.uniqid(),
            'commission_rate' => 5,
            'monthly_target' => 0,
            'is_active' => true,
            'status' => 'approved',
            'verification_status' => 'verified',
            'payout_status' => 'verified',
            'payout_method' => 'mobile_money',
            'payout_account_name' => 'Agent Account',
            'payout_account_number' => '255700000000',
            'currency' => 'TZS',
        ], $attrs));
    }

    protected function agentOrder(string $status = 'delivered', string $payment = 'paid', float $total = 100000, array $attrs = []): Order
    {
        return Order::create(array_merge([
            'status' => $status,
            'payment_status' => $payment,
            'total_amount' => $total,
            'subtotal' => $total,
            'delivery_fee' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'coupon_code' => null,
            'currency' => 'TZS',
        ], $attrs));
    }

    protected function attributeAgent(Order $order, SalesRep $agent): SalesAttribution
    {
        if (! app(CommissionPolicyService::class)->activePolicy()) {
            $this->agentPolicy();
        }

        return app(AgentAttributionService::class)->attribute($order, $agent);
    }
}
