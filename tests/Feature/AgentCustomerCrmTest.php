<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerFollowUp;
use App\Models\SalesRep;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AgentCustomerCrmTest extends TestCase
{
    use DatabaseTransactions;

    protected function agentUser(): array
    {
        $user = User::factory()->create([
            'name' => 'Agent User',
            'email' => 'agent-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
            'account_status' => 'active',
            'portal_access' => true,
        ]);

        $agent = SalesRep::create([
            'user_id' => $user->id,
            'code' => 'AG'.strtoupper(uniqid()),
            'name' => 'Agent User',
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

    public function test_agent_can_create_customer_and_prevent_duplicates(): void
    {
        [$user, $agent] = $this->agentUser();

        $this->actingAs($user)
            ->post(route('agent.customers.store'), [
                'name' => 'Jane Customer',
                'phone' => '0712345678',
                'email' => 'jane@example.com',
                'notes' => 'First contact',
            ])
            ->assertRedirect();

        $customer = Customer::where('phone', '0712345678')->firstOrFail();
        $this->assertSame($agent->id, $customer->sales_rep_id);
        $this->assertSame('new', $customer->crm_status);
        $this->assertDatabaseHas('customer_notes', ['customer_id' => $customer->id]);

        $this->actingAs($user)
            ->post(route('agent.customers.store'), [
                'name' => 'Duplicate',
                'phone' => '0712345678',
            ])
            ->assertRedirect(route('agent.customers.show', Customer::where('phone', '0712345678')->first()));

        $this->assertSame(1, Customer::where('phone', '0712345678')->count());
    }

    public function test_customer_profile_update_and_notes(): void
    {
        [$user, $agent] = $this->agentUser();

        $customer = Customer::create([
            'name' => 'John Customer',
            'email' => 'john@example.com',
            'password' => Hash::make('password'),
            'phone' => '0722222222',
            'sales_rep_id' => $agent->id,
            'status' => 'active',
            'crm_status' => 'new',
        ]);

        $this->actingAs($user)
            ->put(route('agent.customers.update', $customer), [
                'name' => 'John Updated',
                'phone' => '0722222222',
                'email' => 'john@example.com',
                'crm_status' => 'active',
            ])
            ->assertRedirect(route('agent.customers.show', $customer));

        $this->assertSame('John Updated', $customer->fresh()->name);

        $this->actingAs($user)
            ->post(route('agent.customers.notes.store', $customer), [
                'note' => 'Called customer',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('customer_notes', [
            'customer_id' => $customer->id,
            'note' => 'Called customer',
        ]);
    }

    public function test_followup_schedule_and_complete(): void
    {
        [$user, $agent] = $this->agentUser();

        $customer = Customer::create([
            'name' => 'Follow Up Customer',
            'email' => 'follow@example.com',
            'password' => Hash::make('password'),
            'phone' => '0733333333',
            'sales_rep_id' => $agent->id,
            'status' => 'active',
            'crm_status' => 'new',
        ]);

        $this->actingAs($user)
            ->post(route('agent.customers.followups.store', $customer), [
                'reason' => 'Check in',
                'notes' => 'Ask about repeat order',
                'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'),
            ])
            ->assertRedirect();

        $followUp = CustomerFollowUp::where('customer_id', $customer->id)->firstOrFail();
        $this->assertNull($followUp->completed_at);

        $this->actingAs($user)
            ->post(route('agent.customers.followups.complete', $followUp))
            ->assertRedirect();

        $this->assertNotNull($followUp->fresh()->completed_at);
    }

    public function test_ownership_restricts_access(): void
    {
        [$user, $agent] = $this->agentUser();
        [$otherUser, $otherAgent] = $this->agentUser();

        $customer = Customer::create([
            'name' => 'Owned Customer',
            'email' => 'owned@example.com',
            'password' => Hash::make('password'),
            'phone' => '0744444444',
            'sales_rep_id' => $otherAgent->id,
            'status' => 'active',
            'crm_status' => 'new',
        ]);

        $this->actingAs($user)
            ->get(route('agent.customers.show', $customer))
            ->assertForbidden();
    }

    public function test_customer_directory_filters_and_place_order(): void
    {
        [$user, $agent] = $this->agentUser();

        $customer = Customer::create([
            'name' => 'Searchable Customer',
            'email' => 'search@example.com',
            'password' => Hash::make('password'),
            'phone' => '0755555555',
            'sales_rep_id' => $agent->id,
            'status' => 'active',
            'crm_status' => 'active',
        ]);

        $this->actingAs($user)
            ->get(route('agent.customers.index', ['q' => 'Searchable']))
            ->assertOk()
            ->assertSee('Searchable Customer');

        $this->actingAs($user)
            ->get(route('agent.customers.place-order', $customer))
            ->assertRedirect(route('agent.place-order.index', ['customer_id' => $customer->id]));
    }
}
