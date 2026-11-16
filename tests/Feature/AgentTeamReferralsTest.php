<?php

namespace Tests\Feature;

use App\Models\SalesRep;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AgentTeamReferralsTest extends TestCase
{
    use DatabaseTransactions;

    protected function agentUser(): array
    {
        $user = User::factory()->create(['name' => 'Team Agent', 'email' => 'team-agent-'.uniqid().'@example.com', 'password' => Hash::make('password'), 'account_status' => 'active', 'portal_access' => true]);
        $agent = SalesRep::create(['user_id' => $user->id, 'code' => 'AG'.strtoupper(uniqid()), 'name' => 'Team Agent', 'email' => $user->email, 'commission_rate' => 5, 'monthly_target' => 0, 'is_active' => true, 'status' => 'approved', 'verification_status' => 'verified', 'currency' => 'TZS']);

        return [$user, $agent];
    }

    public function test_team_page_and_invitation()
    {
        [$user,$agent] = $this->agentUser();
        $this->actingAs($user)->get(route('agent.referrals.index'))->assertOk()->assertSee('My Team / Referrals');
        $this->actingAs($user)->post(route('agent.team.invitations.store'), ['name' => 'Invitee', 'email' => 'invitee@example.com'])->assertRedirect();
        $this->assertDatabaseHas('agent_invitations', ['inviter_sales_rep_id' => $agent->id, 'email' => 'invitee@example.com']);
    }
}
