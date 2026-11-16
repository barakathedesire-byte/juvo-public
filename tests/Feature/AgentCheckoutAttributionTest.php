<?php

namespace Tests\Feature;

use App\Models\AgentCommissionEntry;
use App\Models\AgentCommissionPolicy;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesAttribution;
use App\Models\SalesRep;
use App\Models\Shop;
use App\Models\Vendor;
use App\Services\Sales\CommissionPolicyService;
use App\Services\Store\OrderService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AgentCheckoutAttributionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        AgentCommissionPolicy::query()->update(['is_active' => false]);
    }

    protected function agent(): SalesRep
    {
        return SalesRep::create([
            'code' => 'AG'.strtoupper(uniqid()),
            'name' => 'Agent '.uniqid(),
            'commission_rate' => 5,
            'monthly_target' => 0,
            'is_active' => true,
            'status' => 'approved',
            'verification_status' => 'verified',
            'currency' => 'TZS',
        ]);
    }

    protected function line(): array
    {
        $vendor = Vendor::create([
            'name' => 'Referrer',
            'shop_name' => 'Referral Shop',
            'email' => uniqid('ref').'@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
            'is_approved' => true,
            'email_verified_at' => now(),
        ]);

        $shop = Shop::firstOrCreate(
            ['vendor_id' => $vendor->id],
            ['name' => $vendor->shop_name, 'slug' => 'shop-'.uniqid(), 'status' => 'active']
        );

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
        $product->translation()->create(['language_code' => 'en', 'name' => 'Referral Product', 'description' => 'desc']);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'SKU' => 'SKU-'.uniqid(),
            'price' => 200000,
            'stock' => 10,
            'is_primary' => 1,
            'variant_slug' => 'v-'.uniqid(),
        ]);

        return [
            'product' => $product->fresh(),
            'variant' => $variant,
            'quantity' => 1,
            'unit_price' => 200000.0,
        ];
    }

    public function test_checkout_attributes_order_to_referring_agent(): void
    {
        app(CommissionPolicyService::class)->create([
            'name' => 'Checkout policy',
            'policy_key' => 'checkout-'.uniqid(),
            'rate' => 5,
            'basis' => 'order_total',
            'is_active' => true,
            'is_default' => false,
        ]);

        $agent = $this->agent();
        session(['sales_ref_code' => $agent->code]);

        $orders = app(OrderService::class)->createOrders([$this->line()], [], null, [
            'guest_email' => 'guest@example.com',
            'is_guest' => true,
            'payment_method' => 'cod',
            'status' => 'pending',
            'region' => null,
            'notify' => false,
        ]);

        $order = $orders->first();

        $this->assertDatabaseHas('sales_attributions', [
            'order_id' => $order->id,
            'sales_rep_id' => $agent->id,
        ]);

        $this->assertSame(1, AgentCommissionEntry::where('order_id', $order->id)->where('role', 'direct')->count());
        $this->assertNull(session('sales_ref_code'));
    }

    public function test_checkout_without_referral_creates_no_attribution(): void
    {
        $this->agent();

        $orders = app(OrderService::class)->createOrders([$this->line()], [], null, [
            'guest_email' => 'guest@example.com',
            'is_guest' => true,
            'payment_method' => 'cod',
            'status' => 'pending',
            'region' => null,
            'notify' => false,
        ]);

        $this->assertSame(0, SalesAttribution::where('order_id', $orders->first()->id)->count());
    }
}
