<?php

namespace Tests\Feature;

use App\Models\LeadFollowUp;
use App\Models\SalesLead;
use App\Models\SalesRep;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AgentLeadsCrmTest extends TestCase
{
    use DatabaseTransactions;

    protected function agentUser(): array
    {
        $user = User::factory()->create([
            'name' => 'Lead Agent',
            'email' => 'lead-agent-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
            'account_status' => 'active',
            'portal_access' => true,
        ]);

        $agent = SalesRep::create([
            'user_id' => $user->id,
            'code' => 'AG'.strtoupper(uniqid()),
            'name' => 'Lead Agent',
            'email' => $user->email,
            'commission_rate' => 5,
            'monthly_target' => 0,
            'is_active' => true,
            'status' => 'approved',
            'verification_status' => 'verified',
            'currency' => 'TZS',
        ]);

        return [$user, $agent];
    }

    public function test_lead_create_duplicate_and_status(): void
    {
        [$user, $agent] = $this->agentUser();

        $this->actingAs($user)
            ->post(route('agent.leads.store'), [
                'name' => 'Lead One',
                'phone' => '0799999999',
                'source' => 'whatsapp',
            ])
            ->assertRedirect();

        $lead = SalesLead::where('phone', '0799999999')->firstOrFail();
        $this->assertSame($agent->id, $lead->sales_rep_id);

        $this->actingAs($user)
            ->post(route('agent.leads.store'), [
                'name' => 'Duplicate Lead',
                'phone' => '0799999999',
                'source' => 'whatsapp',
            ])
            ->assertRedirect(route('agent.leads.show', $lead));

        $this->actingAs($user)
            ->post(route('agent.leads.status', $lead), [
                'status' => 'lost',
                'lost_reason' => 'not_interested',
            ])
            ->assertRedirect();

        $this->assertSame('lost', $lead->fresh()->status);
        $this->assertSame('not_interested', $lead->fresh()->lost_reason);
    }

    public function test_lead_notes_followups_and_conversion(): void
    {
        [$user, $agent] = $this->agentUser();

        $lead = SalesLead::create([
            'sales_rep_id' => $agent->id,
            'name' => 'Convertible Lead',
            'phone' => '0788888888',
            'email' => 'convert@example.com',
            'source' => 'social_media',
            'status' => 'interested',
        ]);

        $this->actingAs($user)
            ->post(route('agent.leads.notes.store', $lead), ['note' => 'Interested in bulk order'])
            ->assertRedirect();
        $this->assertDatabaseHas('lead_notes', ['sales_lead_id' => $lead->id]);

        $this->actingAs($user)
            ->post(route('agent.leads.followups.store', $lead), [
                'reason' => 'Call back',
                'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'),
            ])
            ->assertRedirect();
        $followUp = LeadFollowUp::where('sales_lead_id', $lead->id)->firstOrFail();

        $this->actingAs($user)
            ->post(route('agent.leads.followups.complete', $followUp))
            ->assertRedirect();
        $this->assertNotNull($followUp->fresh()->completed_at);

        $this->actingAs($user)
            ->post(route('agent.leads.convert', $lead))
            ->assertRedirect();

        $lead->refresh();
        $this->assertSame('converted', $lead->status);
        $this->assertNotNull($lead->converted_customer_id);
        $this->assertDatabaseHas('customers', ['id' => $lead->converted_customer_id, 'sales_rep_id' => $agent->id]);

        // repeated conversion is safe
        $this->actingAs($user)
            ->post(route('agent.leads.convert', $lead))
            ->assertRedirect(route('agent.customers.show', $lead->converted_customer_id));
    }

    public function test_lead_ownership(): void
    {
        [$user, $agent] = $this->agentUser();
        [$otherUser, $otherAgent] = $this->agentUser();

        $lead = SalesLead::create([
            'sales_rep_id' => $otherAgent->id,
            'name' => 'Other Lead',
            'phone' => '0777777777',
            'source' => 'walk_in',
            'status' => 'new',
        ]);

        $this->actingAs($user)
            ->get(route('agent.leads.show', $lead))
            ->assertForbidden();
    }
}
