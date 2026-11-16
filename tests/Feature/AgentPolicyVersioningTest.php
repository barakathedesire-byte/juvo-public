<?php

namespace Tests\Feature;

use App\Services\Sales\CommissionPolicyService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\BuildsAgentNetwork;
use Tests\TestCase;

class AgentPolicyVersioningTest extends TestCase
{
    use BuildsAgentNetwork;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetAgentPolicies();
    }

    protected function policies()
    {
        return app(CommissionPolicyService::class);
    }

    public function test_a_new_revision_bumps_the_version_and_deactivates_the_prior_one(): void
    {
        $first = $this->agentPolicy(['policy_key' => 'standard', 'rate' => 5]);
        $second = $this->agentPolicy(['policy_key' => 'standard', 'rate' => 7]);

        $this->assertSame(1, $first->version);
        $this->assertSame(2, $second->version);
        $this->assertFalse($first->fresh()->is_active);
        $this->assertTrue($second->fresh()->is_active);
        $this->assertSame($second->id, $this->policies()->activePolicy()->id);
    }

    public function test_active_policy_prefers_the_highest_priority(): void
    {
        $normal = $this->agentPolicy(['priority' => 0, 'rate' => 3]);
        $campaign = $this->agentPolicy(['priority' => 10, 'rate' => 8]);

        $active = $this->policies()->activePolicy();

        $this->assertSame($campaign->id, $active->id);
        $this->assertNotSame($normal->id, $active->id);
    }

    public function test_a_future_dated_policy_is_not_active_yet(): void
    {
        $current = $this->agentPolicy(['is_default' => true, 'rate' => 4]);
        $future = $this->agentPolicy(['effective_from' => now()->addDays(5), 'rate' => 9]);

        $active = $this->policies()->activePolicy();

        $this->assertSame($current->id, $active->id);
        $this->assertNotSame($future->id, $active->id);
    }

    public function test_revising_reuses_the_key_and_remains_active(): void
    {
        $policy = $this->agentPolicy(['policy_key' => 'standard', 'rate' => 5]);

        $revised = $this->policies()->revise($policy, ['name' => 'Standard', 'rate' => 12]);

        $this->assertSame('standard', $revised->policy_key);
        $this->assertSame(2, $revised->version);
        $this->assertTrue($revised->is_active);
        $this->assertFalse($policy->fresh()->is_active);
        $this->assertSame(12.0, (float) $revised->rate);
    }
}
