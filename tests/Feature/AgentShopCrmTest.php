<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesRep;
use App\Models\Shop;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AgentShopCrmTest extends TestCase
{
    use DatabaseTransactions;

    protected function agentUser(): array
    {
        $user = User::factory()->create([
            'name' => 'Shop Agent',
            'email' => 'shop-agent-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
            'account_status' => 'active',
            'portal_access' => true,
        ]);

        $agent = SalesRep::create([
            'user_id' => $user->id,
            'code' => 'AG'.strtoupper(uniqid()),
            'name' => 'Shop Agent',
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

    protected function product(): Product
    {
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
        $product->translation()->create(['language_code' => 'en', 'name' => 'Shop Product', 'description' => 'desc']);

        ProductVariant::create([
            'product_id' => $product->id,
            'SKU' => 'SKU-'.uniqid(),
            'price' => 20000,
            'stock' => 10,
            'is_primary' => 1,
            'variant_slug' => 'v-'.uniqid(),
        ]);

        return $product->fresh();
    }

    public function test_shop_catalogue_search_and_save(): void
    {
        [$user, $agent] = $this->agentUser();
        $product = $this->product();

        $this->actingAs($user)
            ->get(route('agent.shop.index', ['q' => 'Shop Product']))
            ->assertOk()
            ->assertSee('Shop Product');

        $this->actingAs($user)
            ->get(route('agent.shop.show', $product))
            ->assertOk()
            ->assertSee('Shop Product');

        $this->actingAs($user)
            ->post(route('agent.shop.save', $product))
            ->assertRedirect();

        $this->actingAs($user)
            ->get(route('agent.shop.saved'))
            ->assertOk()
            ->assertSee('Shop Product');

        $this->actingAs($user)
            ->post(route('agent.shop.compare.add', $product))
            ->assertRedirect();

        $this->actingAs($user)
            ->get(route('agent.shop.compare'))
            ->assertOk()
            ->assertSee('Shop Product');
    }

    public function test_add_to_order_redirects_with_variant(): void
    {
        [$user, $agent] = $this->agentUser();
        $product = $this->product();
        $variant = $product->variants->first();

        $this->actingAs($user)
            ->get(route('agent.shop.add-to-order', $product))
            ->assertRedirect(route('agent.place-order.index', ['variant_id' => $variant->id, 'quantity' => 1]));
    }
}
