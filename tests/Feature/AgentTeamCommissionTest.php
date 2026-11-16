<?php

namespace Tests\Feature;

use App\Models\AgentCommissionEntry;
use App\Services\Sales\AgentCommissionEngine;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\BuildsAgentNetwork;
use Tests\TestCase;

class AgentTeamCommissionTest extends TestCase
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

    public function test_a_sponsor_earns_a_team_commission_on_a_downline_sale(): void
    {
        $policy = $this->agentPolicy(['team_enabled' => true, 'team_rate' => 2, 'team_max_depth' => 1]);
        $sponsor = $this->agent();
        $agent = $this->agent(['sponsor_id' => $sponsor->id]);
        $order = $this->agentOrder('delivered', 'paid', 100000);

        $entries = $this->engine()->recordForOrder($order, $agent, $policy);

        $this->assertCount(2, $entries);
        $team = AgentCommissionEntry::where('agent_id', $sponsor->id)->firstOrFail();
        $this->assertSame('team', $team->role);
        $this->assertSame(2000.0, (float) $team->amount);
    }

    public function test_team_rate_is_capped_by_the_total_rate_ceiling(): void
    {
        $policy = $this->agentPolicy([
            'team_enabled' => true, 'team_rate' => 2, 'team_max_depth' => 1, 'team_max_total_rate' => 1,
        ]);
        $sponsor = $this->agent();
        $agent = $this->agent(['sponsor_id' => $sponsor->id]);
        $order = $this->agentOrder('delivered', 'paid', 100000);

        $this->engine()->recordForOrder($order, $agent, $policy);

        $team = AgentCommissionEntry::where('agent_id', $sponsor->id)->firstOrFail();
        $this->assertSame(1000.0, (float) $team->amount);
    }

    public function test_a_non_operational_sponsor_earns_nothing(): void
    {
        $policy = $this->agentPolicy(['team_enabled' => true, 'team_rate' => 2, 'team_max_depth' => 1]);
        $sponsor = $this->agent(['status' => 'suspended', 'is_active' => false]);
        $agent = $this->agent(['sponsor_id' => $sponsor->id]);
        $order = $this->agentOrder('delivered', 'paid', 100000);

        $entries = $this->engine()->recordForOrder($order, $agent, $policy);

        $this->assertCount(1, $entries);
        $this->assertSame(0, AgentCommissionEntry::where('agent_id', $sponsor->id)->count());
    }

    public function test_team_depth_zero_disables_team_commissions(): void
    {
        $policy = $this->agentPolicy(['team_enabled' => true, 'team_rate' => 2, 'team_max_depth' => 0]);
        $sponsor = $this->agent();
        $agent = $this->agent(['sponsor_id' => $sponsor->id]);
        $order = $this->agentOrder('delivered', 'paid', 100000);

        $entries = $this->engine()->recordForOrder($order, $agent, $policy);

        $this->assertCount(1, $entries);
        $this->assertSame(0, AgentCommissionEntry::where('agent_id', $sponsor->id)->count());
    }

    public function test_team_commissions_are_off_by_default(): void
    {
        $policy = $this->agentPolicy(['team_enabled' => false]);
        $sponsor = $this->agent();
        $agent = $this->agent(['sponsor_id' => $sponsor->id]);
        $order = $this->agentOrder('delivered', 'paid', 100000);

        $entries = $this->engine()->recordForOrder($order, $agent, $policy);

        $this->assertCount(1, $entries);
    }
}
