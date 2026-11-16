<?php

namespace Tests\Feature;

use App\Models\AgentCommissionEntry;
use App\Services\Sales\AgentCommissionEngine;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\BuildsAgentNetwork;
use Tests\TestCase;

class AgentEligibilityRulesTest extends TestCase
{
    use BuildsAgentNetwork;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetAgentPolicies();
    }

    protected function engine()
    {
        return app(AgentCommissionEngine::class);
    }

    public function test_guest_orders_are_blocked_when_policy_disallows_them(): void
    {
        $policy = $this->agentPolicy(['allow_guest_orders' => false]);
        $agent = $this->agent();
        $order = $this->agentOrder(attrs: ['is_guest' => true, 'guest_email' => 'guest@example.com']);

        $this->assertSame([], $this->engine()->recordForOrder($order, $agent, $policy));
        $this->assertSame(0, AgentCommissionEntry::where('order_id', $order->id)->count());
    }

    public function test_guest_orders_are_allowed_when_policy_permits_them(): void
    {
        $policy = $this->agentPolicy(['allow_guest_orders' => true]);
        $agent = $this->agent();
        $order = $this->agentOrder(attrs: ['is_guest' => true, 'guest_email' => 'guest@example.com']);

        $entries = $this->engine()->recordForOrder($order, $agent, $policy);

        $this->assertCount(1, $entries);
        $this->assertSame('direct', $entries[0]->role);
    }

    public function test_self_purchase_is_blocked_when_policy_disallows_it(): void
    {
        $policy = $this->agentPolicy(['allow_self_purchase' => false]);
        $agent = $this->agent(['email' => 'agent@example.com']);
        $order = $this->agentOrder(attrs: ['guest_email' => 'agent@example.com']);

        $this->assertSame([], $this->engine()->recordForOrder($order, $agent, $policy));
    }

    public function test_self_purchase_is_allowed_when_policy_permits_it(): void
    {
        $policy = $this->agentPolicy(['allow_self_purchase' => true]);
        $agent = $this->agent(['email' => 'agent@example.com']);
        $order = $this->agentOrder(attrs: ['guest_email' => 'agent@example.com']);

        $this->assertCount(1, $this->engine()->recordForOrder($order, $agent, $policy));
    }

    public function test_minimum_order_value_blocks_small_orders(): void
    {
        $policy = $this->agentPolicy(['min_order_value' => 200000]);
        $agent = $this->agent();
        $order = $this->agentOrder(total: 100000);

        $this->assertSame([], $this->engine()->recordForOrder($order, $agent, $policy));
    }

    public function test_minimum_order_value_allows_orders_at_or_above_threshold(): void
    {
        $policy = $this->agentPolicy(['min_order_value' => 200000]);
        $agent = $this->agent();
        $order = $this->agentOrder(total: 300000);

        $entries = $this->engine()->recordForOrder($order, $agent, $policy);

        $this->assertCount(1, $entries);
        $this->assertSame(15000.0, (float) $entries[0]->amount);
    }

    public function test_max_commission_caps_the_payout(): void
    {
        $policy = $this->agentPolicy(['max_commission' => 3000]);
        $agent = $this->agent();
        $order = $this->agentOrder(total: 100000);

        $entries = $this->engine()->recordForOrder($order, $agent, $policy);

        $this->assertCount(1, $entries);
        $this->assertSame(3000.0, (float) $entries[0]->amount);
    }
}
