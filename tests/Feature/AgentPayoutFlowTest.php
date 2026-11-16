<?php

namespace Tests\Feature;

use App\Models\AgentCommissionEntry;
use App\Models\AgentPayoutItem;
use App\Models\BusinessRule;
use App\Models\SalesRep;
use App\Services\Rules\RuleEngine;
use App\Services\Sales\AgentCommissionEngine;
use App\Services\Sales\AgentPayoutService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use RuntimeException;
use Tests\Concerns\BuildsAgentNetwork;
use Tests\TestCase;

class AgentPayoutFlowTest extends TestCase
{
    use BuildsAgentNetwork;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetAgentPolicies();
    }

    protected function fundedAgent(float $total = 100000): SalesRep
    {
        $this->agentPolicy();
        $agent = $this->agent();
        $order = $this->agentOrder('delivered', 'paid', $total);
        $this->attributeAgent($order, $agent);
        app(AgentCommissionEngine::class)->markEligibleForOrder($order);

        return $agent;
    }

    protected function payouts()
    {
        return app(AgentPayoutService::class);
    }

    public function test_requesting_a_payout_reserves_the_commission_entries(): void
    {
        $agent = $this->fundedAgent();

        $payout = $this->payouts()->request($agent);

        $this->assertSame('requested', $payout->status);
        $this->assertSame(5000.0, (float) $payout->amount);
        $this->assertSame(1, AgentPayoutItem::where('agent_payout_request_id', $payout->id)->count());

        $entry = AgentCommissionEntry::where('agent_id', $agent->id)->where('role', 'direct')->firstOrFail();
        $this->assertSame('payable', $entry->state);
        $this->assertSame($payout->id, $entry->payout_request_id);

        $this->assertSame(5000.0, $this->payouts()->balance($agent)['reserved']);
    }

    public function test_rejecting_a_payout_releases_the_entries_back(): void
    {
        $agent = $this->fundedAgent();
        $payout = $this->payouts()->request($agent);

        $this->payouts()->reject($payout, 1, 'incorrect account');

        $entry = AgentCommissionEntry::where('agent_id', $agent->id)->where('role', 'direct')->firstOrFail();
        $this->assertSame('eligible', $entry->state);
        $this->assertNull($entry->payout_request_id);
        $this->assertSame('rejected', $payout->fresh()->status);
    }

    public function test_a_failed_payout_releases_the_entries_back(): void
    {
        $agent = $this->fundedAgent();
        $payout = $this->payouts()->request($agent);

        $this->payouts()->markFailed($payout, 1, 'provider timeout');

        $entry = AgentCommissionEntry::where('agent_id', $agent->id)->where('role', 'direct')->firstOrFail();
        $this->assertSame('eligible', $entry->state);
        $this->assertSame('failed', $payout->fresh()->status);
    }

    public function test_marking_a_payout_paid_settles_the_entries(): void
    {
        $agent = $this->fundedAgent();
        $payout = $this->payouts()->request($agent);

        $this->payouts()->approve($payout, 1);
        $this->payouts()->markProcessing($payout, 1);
        $this->payouts()->markPaid($payout, 1, 'MOMO-123');

        $entry = AgentCommissionEntry::where('agent_id', $agent->id)->where('role', 'direct')->firstOrFail();
        $this->assertSame('paid', $entry->state);
        $this->assertNotNull($entry->paid_at);

        $balance = $this->payouts()->balance($agent);
        $this->assertSame(5000.0, $balance['paid']);
        $this->assertSame(0.0, $balance['reserved']);
        $this->assertSame('MOMO-123', $payout->fresh()->provider_reference);
    }

    public function test_a_second_open_payout_request_is_rejected(): void
    {
        $agent = $this->fundedAgent();
        $this->payouts()->request($agent);

        $this->expectException(RuntimeException::class);

        $this->payouts()->request($agent);
    }

    public function test_requesting_more_than_the_available_balance_is_rejected(): void
    {
        $agent = $this->fundedAgent();

        $this->expectException(RuntimeException::class);

        $this->payouts()->request($agent, 9000);
    }

    public function test_requesting_below_the_configured_minimum_is_rejected(): void
    {
        $agent = $this->fundedAgent();

        BusinessRule::create([
            'key' => 'agent_min_payout',
            'name' => 'Agent minimum payout',
            'value_type' => 'number',
            'value' => '10000',
            'is_active' => true,
            'is_managed' => false,
            'is_editable' => true,
        ]);
        app(RuleEngine::class)->flush();

        $this->expectException(RuntimeException::class);

        $this->payouts()->request($agent);
    }

    public function test_agent_without_verified_payout_account_cannot_request(): void
    {
        $agent = $this->fundedAgent();
        $agent->update(['payout_status' => 'pending']);

        $this->expectException(RuntimeException::class);

        $this->payouts()->request($agent->fresh());
    }
}
