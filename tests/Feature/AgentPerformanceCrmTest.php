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

class AgentPerformanceCrmTest extends TestCase
{
    use DatabaseTransactions;

    public function test_performance_dashboard_loads(): void
    {
        $user = User::factory()->create([
            'name' => 'Perf Agent',
            'email' => 'perf-agent-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
            'account_status' => 'active',
            'portal_access' => true,
        ]);

        $agent = SalesRep::create([
            'user_id' => $user->id,
            'code' => 'AG'.strtoupper(uniqid()),
            'name' => 'Perf Agent',
            'email' => $user->email,
            'commission_rate' => 5,
            'monthly_target' => 100000,
            'is_active' => true,
            'status' => 'approved',
            'verification_status' => 'verified',
            'currency' => 'TZS',
        ]);

        $customer = Customer::create([
            'name' => 'Perf Customer',
            'email' => 'perf-cust@example.com',
            'password' => Hash::make('password'),
            'phone' => '0712345678',
            'sales_rep_id' => $agent->id,
            'status' => 'active',
            'crm_status' => 'active',
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'total_amount' => 50000,
            'subtotal' => 50000,
            'status' => 'delivered',
            'payment_status' => 'paid',
            'payment_method' => 'cod',
        ]);

        SalesAttribution::create([
            'order_id' => $order->id,
            'sales_rep_id' => $agent->id,
            'method' => 'assisted',
            'source' => 'assisted',
            'status' => 'approved',
            'commission_amount' => 2500,
            'attributed_at' => now(),
            'agent_confirmed' => true,
        ]);

        $this->actingAs($user)
            ->get(route('agent.performance.index'))
            ->assertOk()
            ->assertSee('My Sales & Performance')
            ->assertSee('50,000.00');

        $this->actingAs($user)
            ->get(route('agent.performance.index', ['export' => 'csv']))
            ->assertOk();
    }
}
