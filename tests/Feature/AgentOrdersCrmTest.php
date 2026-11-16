<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\SalesAttribution;
use App\Models\SalesRep;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AgentOrdersCrmTest extends TestCase
{
    use DatabaseTransactions;

    protected function agentUser(): array
    {
        $user = User::factory()->create([
            'name' => 'Order Agent',
            'email' => 'order-agent-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
            'account_status' => 'active',
            'portal_access' => true,
        ]);

        $agent = SalesRep::create([
            'user_id' => $user->id,
            'code' => 'AG'.strtoupper(uniqid()),
            'name' => 'Order Agent',
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

    public function test_orders_directory_and_detail_with_issue(): void
    {
        [$user, $agent] = $this->agentUser();

        $customer = Customer::create([
            'name' => 'Order Customer',
            'email' => 'oc@example.com',
            'password' => Hash::make('password'),
            'phone' => '0711111111',
            'sales_rep_id' => $agent->id,
            'status' => 'active',
            'crm_status' => 'active',
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'total_amount' => 10000,
            'subtotal' => 10000,
            'status' => 'delivered',
            'payment_status' => 'paid',
            'payment_method' => 'cod',
        ]);

        SalesAttribution::create([
            'order_id' => $order->id,
            'sales_rep_id' => $agent->id,
            'method' => 'assisted',
            'source' => 'assisted',
            'status' => 'pending',
            'attributed_at' => now(),
            'agent_confirmed' => false,
        ]);

        $this->actingAs($user)
            ->get(route('agent.orders.index'))
            ->assertOk()
            ->assertSee((string) $order->id);

        $this->actingAs($user)
            ->get(route('agent.orders.show', $order))
            ->assertOk()
            ->assertSee('Order #'.$order->id);

        $this->actingAs($user)
            ->post(route('agent.orders.issues.store', $order), [
                'issue_type' => 'delayed_delivery',
                'description' => 'Delivery took too long',
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('agent.orders.returns.store', $order), [
                'reason' => 'Customer changed mind about the product',
            ])
            ->assertRedirect();
    }

    public function test_ownership_restricts_order_access(): void
    {
        [$user, $agent] = $this->agentUser();
        [$otherUser, $otherAgent] = $this->agentUser();

        $order = Order::create([
            'total_amount' => 5000,
            'subtotal' => 5000,
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'cod',
        ]);

        SalesAttribution::create([
            'order_id' => $order->id,
            'sales_rep_id' => $otherAgent->id,
            'method' => 'assisted',
            'source' => 'assisted',
            'status' => 'pending',
            'attributed_at' => now(),
            'agent_confirmed' => false,
        ]);

        $this->actingAs($user)
            ->get(route('agent.orders.show', $order))
            ->assertForbidden();
    }
}
