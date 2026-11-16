<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\SalesRep;
use App\Models\Shop;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AgentDealsCrmTest extends TestCase
{
    use DatabaseTransactions;

    protected function agentUser(): array
    {
        $user = User::factory()->create([
            'name' => 'Deals Agent',
            'email' => 'deals-agent-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
            'account_status' => 'active',
            'portal_access' => true,
        ]);

        $agent = SalesRep::create([
            'user_id' => $user->id,
            'code' => 'AG'.strtoupper(uniqid()),
            'name' => 'Deals Agent',
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

    public function test_deals_discovery_and_share(): void
    {
        [$user, $agent] = $this->agentUser();

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
        $product->translation()->create(['language_code' => 'en', 'name' => 'Deal Product', 'description' => 'desc']);

        ProductVariant::create([
            'product_id' => $product->id,
            'SKU' => 'SKU-'.uniqid(),
            'price' => 20000,
            'discount_price' => 15000,
            'stock' => 10,
            'is_primary' => 1,
            'variant_slug' => 'v-'.uniqid(),
        ]);

        $promotion = Promotion::create([
            'vendor_id' => $vendor->id,
            'product_id' => $product->id,
            'title' => 'Spring Deal',
            'type' => 'percent',
            'value' => 25,
            'active' => true,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(5),
        ]);

        $this->actingAs($user)
            ->get(route('agent.deals.index'))
            ->assertOk()
            ->assertSee('Spring Deal');

        $this->actingAs($user)
            ->get(route('agent.deals.show', $promotion))
            ->assertOk()
            ->assertSee('Spring Deal');

        $this->actingAs($user)
            ->post(route('agent.deals.share', $promotion), ['product_id' => $product->id, 'channel' => 'link'])
            ->assertRedirect(route('agent.shop.show', $product));

        $this->assertDatabaseHas('agent_promotion_shares', [
            'sales_rep_id' => $agent->id,
            'promotion_id' => $promotion->id,
            'product_id' => $product->id,
        ]);
    }
}
