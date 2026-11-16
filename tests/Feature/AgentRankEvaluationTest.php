<?php

namespace Tests\Feature;

use App\Models\AgentRankDefinition;
use App\Models\AgentRankHistory;
use App\Services\Sales\AgentRankService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\BuildsAgentNetwork;
use Tests\TestCase;

class AgentRankEvaluationTest extends TestCase
{
    use BuildsAgentNetwork;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetAgentPolicies();
    }

    protected function ranks()
    {
        return app(AgentRankService::class);
    }

    protected function ladder(): array
    {
        return [
            AgentRankDefinition::create([
                'name' => 'Bronze', 'slug' => 'bronze-'.uniqid(), 'level' => 1,
                'min_sales_volume' => 0, 'min_delivered_orders' => 0, 'is_active' => true,
            ]),
            AgentRankDefinition::create([
                'name' => 'Silver', 'slug' => 'silver-'.uniqid(), 'level' => 2,
                'min_sales_volume' => 50000, 'min_delivered_orders' => 1, 'is_active' => true,
            ]),
        ];
    }

    public function test_an_agent_is_promoted_when_it_crosses_a_rank_threshold(): void
    {
        $this->ladder();
        $agent = $this->agent();

        $first = $this->ranks()->evaluate($agent, '2026-10');
        $this->assertSame('Bronze', $first->rank->name);
        $this->assertFalse($first->promoted);

        $this->agentPolicy();
        $order = $this->agentOrder('delivered', 'paid', 100000);
        $this->attributeAgent($order, $agent);

        $second = $this->ranks()->evaluate($agent->fresh(), '2026-10');

        $this->assertSame('Silver', $second->rank->name);
        $this->assertTrue($second->promoted);
        $this->assertSame($first->rank_definition_id, $second->previous_rank_id);
        $this->assertSame(1, $second->delivered_orders);
    }

    public function test_an_agent_without_qualifying_volume_gets_no_rank(): void
    {
        AgentRankDefinition::create([
            'name' => 'Elite', 'slug' => 'elite-'.uniqid(), 'level' => 9,
            'min_sales_volume' => 1000000, 'min_delivered_orders' => 50, 'is_active' => true,
        ]);

        $agent = $this->agent();
        $history = $this->ranks()->evaluate($agent, '2026-10');

        $this->assertNull($history->rank_definition_id);
        $this->assertStringContainsString('No qualifying rank', $history->reason);
    }

    public function test_evaluation_records_a_history_row(): void
    {
        $this->ladder();
        $agent = $this->agent();

        $this->ranks()->evaluate($agent, '2026-10');

        $this->assertSame(1, AgentRankHistory::where('agent_id', $agent->id)->count());
        $this->assertDatabaseHas('agent_rank_histories', [
            'agent_id' => $agent->id,
            'period' => '2026-10',
        ]);
    }

    public function test_evaluate_all_covers_operational_agents(): void
    {
        $this->ladder();
        $this->agent();
        $this->agent();
        $this->agent(['status' => 'suspended']);

        $this->assertSame(2, $this->ranks()->evaluateAll('2026-10'));
    }
}
