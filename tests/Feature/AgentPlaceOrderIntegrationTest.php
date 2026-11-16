<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesRep;
use App\Models\Shop;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AgentPlaceOrderIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_agent_can_place_order_for_customer(): void
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

        $customer = Customer::create([
            'name' => 'Order Customer',
            'email' => 'ordercust@example.com',
            'password' => Hash::make('password'),
            'phone' => '0766666666',
            'sales_rep_id' => $agent->id,
            'status' => 'active',
            'crm_status' => 'active',
        ]);

        $vendor = Vendor::create([
            'name' => 'Vendor',
            'shop_name' => 'Shop',
            'email' => uniqid('v').'@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
            'is_approved' => true,
            'email_verified_at' => now(),
        ]);

        $shop = Shop::firstOrCreate(['vendor_id' => $vendor->id], ['name' => $vendor->shop_name, 'slug' => 'shop-'.uniqid(), 'status' => 'active']);
        $product = Product::create([
            'shop_id' => $shop->id,
            'vendor_id' => $vendor->id,
            'category_id' => \App\Models\Category::first()->id,
            'slug' => 'p-'.uniqid(),
            'product_type' => 'simple',
            'status' => 1,
            'approval_status' => 'approved',
            'visibility' => 'public',
        ]);
        $product->translation()->create(['language_code' => 'en', 'name' => 'Test Product', 'description' => 'desc']);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'SKU' => 'SKU-'.uniqid(),
            'price' => 20000,
            'stock' => 10,
            'is_primary' => 1,
            'variant_slug' => 'v-'.uniqid(),
        ]);

        $this->actingAs($user)
            ->post(route('agent.place-order.store'), [
                'customer_id' => $customer->id,
                'variant_ids' => [$variant->id],
                'quantities' => [2],
                'payment_method' => 'cod',
                'idempotency_key' => 'test-key-'.uniqid(),
            ])
            ->assertRedirect(route('agent.orders.index'));

        $this->assertDatabaseHas('orders', [
            'customer_id' => $customer->id,
            'status' => 'pending',
        ]);

        $order = \App\Models\Order::where('customer_id', $customer->id)->latest()->first();
        $this->assertDatabaseHas('sales_attributions', [
            'order_id' => $order->id,
            'sales_rep_id' => $agent->id,
        ]);
    }
}
