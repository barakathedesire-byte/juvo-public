<?php

namespace Tests\Feature;

use App\Models\AgentCommissionEntry;
use App\Services\Sales\AgentFraudService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\BuildsAgentNetwork;
use Tests\TestCase;

class AgentFraudHoldTest extends TestCase
{
    use BuildsAgentNetwork;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetAgentPolicies();
    }

    protected function fraud()
    {
        return app(AgentFraudService::class);
    }

    public function test_a_self_purchase_flags_high_severity_and_holds_pending_commission(): void
    {
        $this->agentPolicy(['allow_self_purchase' => true]);
        $agent = $this->agent(['email' => 'agent@example.com']);
        $order = $this->agentOrder('delivered', 'paid', attrs: ['guest_email' => 'agent@example.com']);
        $this->attributeAgent($order, $agent);

        $entry = AgentCommissionEntry::where('agent_id', $agent->id)->firstOrFail();
        $this->assertSame('pending', $entry->state);

        $review = $this->fraud()->scanOrder($order);

        $this->assertSame('self_purchase', $review->type);
        $this->assertSame('high', $review->severity);
        $this->assertSame('held', $entry->fresh()->state);
    }

    public function test_clearing_a_fraud_review_releases_commission_to_eligible(): void
    {
        $this->agentPolicy(['allow_self_purchase' => true]);
        $agent = $this->agent(['email' => 'agent@example.com']);
        $order = $this->agentOrder('delivered', 'paid', attrs: ['guest_email' => 'agent@example.com']);
        $this->attributeAgent($order, $agent);

        $review = $this->fraud()->scanOrder($order);
        $this->fraud()->resolve($review, 1, 'clear', 'legitimate purchase');

        $entry = AgentCommissionEntry::where('agent_id', $agent->id)->firstOrFail();
        $this->assertSame('eligible', $entry->state);
        $this->assertSame('resolved', $review->fresh()->status);
    }

    public function test_confirming_a_fraud_review_reverses_the_commission(): void
    {
        $this->agentPolicy(['allow_self_purchase' => true]);
        $agent = $this->agent(['email' => 'agent@example.com']);
        $order = $this->agentOrder('delivered', 'paid', attrs: ['guest_email' => 'agent@example.com']);
        $this->attributeAgent($order, $agent);

        $review = $this->fraud()->scanOrder($order);
        $this->fraud()->resolve($review, 1, 'confirm', 'agent self-dealing');

        $entry = AgentCommissionEntry::where('agent_id', $agent->id)->where('role', 'direct')->firstOrFail();
        $this->assertSame('reversed', $entry->state);
    }

    public function test_order_velocity_above_threshold_raises_a_review(): void
    {
        $this->agentPolicy();
        $agent = $this->agent();

        for ($i = 0; $i < 11; $i++) {
            $order = $this->agentOrder('delivered', 'paid', 10000);
            $this->attributeAgent($order, $agent);
        }

        $types = collect($this->fraud()->scanAgent($agent))->pluck('type')->all();

        $this->assertContains('velocity', $types);
        $this->assertDatabaseHas('agent_fraud_reviews', [
            'agent_id' => $agent->id,
            'type' => 'velocity',
        ]);
    }

    public function test_high_cancellation_rate_raises_a_review(): void
    {
        $this->agentPolicy();
        $agent = $this->agent();

        for ($i = 0; $i < 4; $i++) {
            $order = $this->agentOrder('canceled', 'refunded', 10000);
            $this->attributeAgent($order, $agent);
        }
        for ($i = 0; $i < 2; $i++) {
            $order = $this->agentOrder('delivered', 'paid', 10000);
            $this->attributeAgent($order, $agent);
        }

        $types = collect($this->fraud()->scanAgent($agent))->pluck('type')->all();

        $this->assertContains('cancellation_rate', $types);
    }
}
