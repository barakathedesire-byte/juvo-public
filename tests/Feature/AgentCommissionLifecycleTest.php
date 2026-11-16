<?php

namespace Tests\Feature;

use App\Models\AgentCommissionEntry;
use App\Models\AgentCommissionPolicy;
use App\Models\Order;
use App\Models\SalesRep;
use App\Services\Sales\AgentAttributionService;
use App\Services\Sales\AgentCommissionEngine;
use App\Services\Sales\CommissionPolicyService;
use App\Services\Vendor\OrderWorkflowService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AgentCommissionLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        AgentCommissionPolicy::query()->update(['is_active' => false]);
    }

    protected function policy(float $rate = 5.0): AgentCommissionPolicy
    {
        return app(CommissionPolicyService::class)->create([
            'name' => 'Test policy '.uniqid(),
            'policy_key' => 'test-'.uniqid(),
            'rate' => $rate,
            'basis' => 'order_total',
            'is_active' => true,
            'is_default' => false,
        ]);
    }

    protected function agent(): SalesRep
    {
        return SalesRep::create([
            'code' => 'AG'.strtoupper(uniqid()),
            'name' => 'Agent '.uniqid(),
            'commission_rate' => 5,
            'monthly_target' => 0,
            'is_active' => true,
            'status' => 'approved',
            'verification_status' => 'verified',
            'currency' => 'TZS',
        ]);
    }

    protected function order(string $status = 'delivered', string $payment = 'paid', float $total = 100000): Order
    {
        return Order::create([
            'status' => $status,
            'payment_status' => $payment,
            'total_amount' => $total,
            'delivery_fee' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'coupon_code' => null,
        ]);
    }

    public function test_delivered_paid_order_releases_agent_commission(): void
    {
        $this->policy();
        $agent = $this->agent();
        $order = $this->order('delivered', 'paid');

        app(AgentAttributionService::class)->attribute($order, $agent);

        $this->assertSame('pending', AgentCommissionEntry::where('agent_id', $agent->id)->first()->state);

        app(OrderWorkflowService::class)->transition($order, 'completed', null, 'system', null);

        $entry = AgentCommissionEntry::where('agent_id', $agent->id)->first();
        $this->assertSame('eligible', $entry->fresh()->state);

        $balance = app(AgentCommissionEngine::class)->balanceFor($agent);
        $this->assertSame(5000.0, $balance['available']);
        $this->assertSame(0.0, $balance['pending']);
    }

    public function test_canceled_order_reverses_agent_commission(): void
    {
        $this->policy();
        $agent = $this->agent();
        $order = $this->order('processing', 'paid');

        app(AgentAttributionService::class)->attribute($order, $agent);

        app(OrderWorkflowService::class)->transition($order, 'canceled', 'customer changed mind', 'system', null);

        $original = AgentCommissionEntry::where('agent_id', $agent->id)->where('role', 'direct')->first();
        $this->assertSame('reversed', $original->fresh()->state);

        $balance = app(AgentCommissionEngine::class)->balanceFor($agent);
        $this->assertSame(0.0, $balance['available']);
    }

    public function test_attribution_is_idempotent(): void
    {
        $this->policy();
        $agent = $this->agent();
        $order = $this->order('delivered', 'paid');

        app(AgentAttributionService::class)->attribute($order, $agent);
        app(AgentAttributionService::class)->attribute($order, $agent);

        $this->assertSame(1, AgentCommissionEntry::where('agent_id', $agent->id)->where('role', 'direct')->count());
    }
}
