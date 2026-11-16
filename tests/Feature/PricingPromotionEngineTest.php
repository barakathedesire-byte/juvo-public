<?php

namespace Tests\Feature;

use App\Automation\Actions\ExpirePromotionsAction;
use App\Models\AutomationRun;
use App\Models\BusinessRule;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\Vendor;
use App\Services\Intelligence\AlgorithmRegistry;
use App\Services\Pricing\BudgetService;
use App\Services\Pricing\MarginGuardrail;
use App\Services\Pricing\PriceHistoryService;
use App\Services\Store\CheckoutPricingService;
use App\Services\Store\OrderService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class PricingPromotionEngineTest extends TestCase
{
    use DatabaseTransactions;
    use GrantsAdminAccess;

    protected function vendor(): Vendor
    {
        return Vendor::create([
            'name' => 'Pricer',
            'shop_name' => 'Pricing Shop',
            'email' => uniqid('price').'@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
            'is_approved' => true,
            'email_verified_at' => now(),
        ]);
    }

    protected function product(Vendor $vendor, float $price = 1000): array
    {
        $shop = \App\Models\Shop::firstOrCreate(
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
        $product->translation()->create(['language_code' => 'en', 'name' => 'Test Product', 'description' => 'desc']);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'SKU' => 'SKU-'.uniqid(),
            'price' => $price,
            'stock' => 10,
            'is_primary' => 1,
            'variant_slug' => 'v-'.uniqid(),
        ]);

        return [$product->fresh(), $variant];
    }

    public function test_authoritative_quote_exposes_funding_breakdown(): void
    {
        $vendor = $this->vendor();
        [$product] = $this->product($vendor, 1000);

        Promotion::create([
            'title' => 'Juvo basket 10%',
            'type' => 'percent',
            'value' => 10,
            'scope' => 'order',
            'status' => 'active',
            'active' => true,
            'funding_source' => Promotion::FUNDING_JUVO,
        ]);

        $quote = app(CheckoutPricingService::class)->quote([
            ['unit_price' => 1000, 'quantity' => 2, 'product' => $product],
        ]);

        $this->assertEqualsWithDelta(200.0, $quote['discount'], 0.01);
        $this->assertEqualsWithDelta(200.0, $quote['juvo_funded'], 0.01);
        $this->assertEqualsWithDelta(0.0, $quote['vendor_funded'], 0.01);
        $this->assertArrayHasKey('line_breakdown', $quote);
        $this->assertSame('juvo', $quote['discounts'][0]['funding_source']);
    }

    public function test_promotions_stack_best_per_group(): void
    {
        $vendor = $this->vendor();
        [$product] = $this->product($vendor, 1000);

        Promotion::create([
            'title' => 'A 10%', 'type' => 'percent', 'value' => 10, 'scope' => 'order',
            'status' => 'active', 'active' => true, 'stack_group' => 'basket',
            'funding_source' => Promotion::FUNDING_VENDOR,
        ]);
        Promotion::create([
            'title' => 'B 25%', 'type' => 'percent', 'value' => 25, 'scope' => 'order',
            'status' => 'active', 'active' => true, 'stack_group' => 'basket',
            'funding_source' => Promotion::FUNDING_VENDOR,
        ]);

        $quote = app(CheckoutPricingService::class)->quote([
            ['unit_price' => 1000, 'quantity' => 1, 'product' => $product],
        ]);

        $this->assertEqualsWithDelta(250.0, $quote['discount'], 0.01);
    }

    public function test_budget_reservation_is_atomic_and_exhausts(): void
    {
        $vendor = $this->vendor();
        $promotion = Promotion::create([
            'title' => 'Capped', 'type' => 'fixed', 'value' => 100, 'scope' => 'order',
            'status' => 'active', 'active' => true, 'funding_source' => Promotion::FUNDING_JUVO,
            'budget_amount' => 100,
        ]);

        $budgets = app(BudgetService::class);
        $this->assertNotNull($budgets->reserve($promotion, 100, 'ref-1'));
        $this->assertNull($budgets->reserve($promotion, 100, 'ref-2'));

        $budgets->commit('ref-1');
        $this->assertSame('exhausted', $promotion->fresh()->status);
    }

    public function test_margin_guardrail_blocks_excessive_discount(): void
    {
        BusinessRule::updateOrCreate(
            ['key' => 'pricing.max_discount_percent'],
            ['name' => 'Max discount', 'value_type' => 'number', 'value' => '30', 'is_active' => true]
        );

        $result = app(MarginGuardrail::class)->evaluate($this->vendor(), 1000, 500);

        $this->assertSame('block', $result['level']);
        $this->assertFalse($result['allowed']);
    }

    public function test_price_history_records_changes(): void
    {
        $vendor = $this->vendor();
        [$product, $variant] = $this->product($vendor, 1000);

        app(PriceHistoryService::class)->recordVariantChange($product, $variant->id, 'price', 1000, 1200, 'vendor_edit', 'cost update');

        $this->assertDatabaseHas('price_change_logs', [
            'variant_id' => $variant->id,
            'field' => 'price',
            'old_value' => 1000,
            'new_value' => 1200,
        ]);
    }

    public function test_vendor_product_price_edit_records_history(): void
    {
        $vendor = $this->vendor();
        [$product, $variant] = $this->product($vendor, 1000);
        $sku = $variant->SKU;

        $this->actingAs($vendor, 'vendor')->put(route('vendor.products.update', $product->id), [
            'category_id' => $product->category_id,
            'visibility' => 'public',
            'translations' => ['en' => ['name' => 'Test Product', 'description' => 'desc']],
            'variants' => [[
                'name' => 'Default', 'price' => 1200, 'stock' => 10, 'SKU' => $sku,
            ]],
        ])->assertRedirect();

        $this->assertDatabaseHas('price_change_logs', [
            'product_id' => $product->id,
            'field' => 'price',
            'new_value' => 1200,
            'source' => 'vendor_edit',
        ]);
    }

    public function test_pricing_algorithm_is_registered_and_implemented(): void
    {
        $definitions = app(AlgorithmRegistry::class)->definitions();
        $this->assertTrue($definitions['pricing_optimisation']['implemented']);

        app(AlgorithmRegistry::class)->sync();
        $this->assertDatabaseHas('algorithms', ['key' => 'pricing_optimisation', 'is_implemented' => 1]);
    }

    public function test_expire_promotions_action_advances_lifecycle(): void
    {
        $ended = Promotion::create([
            'title' => 'Ended', 'type' => 'percent', 'value' => 10, 'scope' => 'order',
            'status' => 'active', 'active' => true, 'ends_at' => now()->subHour(),
            'funding_source' => Promotion::FUNDING_VENDOR,
        ]);
        $scheduled = Promotion::create([
            'title' => 'Scheduled', 'type' => 'percent', 'value' => 10, 'scope' => 'order',
            'status' => 'scheduled', 'active' => false, 'starts_at' => now()->subHour(),
            'funding_source' => Promotion::FUNDING_VENDOR,
        ]);

        app(ExpirePromotionsAction::class)->handle([], new AutomationRun);

        $this->assertSame('expired', $ended->fresh()->status);
        $this->assertSame('active', $scheduled->fresh()->status);
    }

    public function test_admin_pricing_console_requires_permission(): void
    {
        $this->actingAs($this->admin())->get(route('admin.pricing.index'))->assertOk();
    }

    public function test_vendor_promotions_page_renders_advisory_recommendations(): void
    {
        $vendor = $this->vendor();
        $this->product($vendor, 1000);

        $this->actingAs($vendor, 'vendor')
            ->get(route('vendor.promotions.index'))
            ->assertOk()
            ->assertSee('Advisory Recommendations');
    }

    public function test_admin_can_create_funded_promotion_queued_for_approval(): void
    {
        $vendor = $this->vendor();

        $this->actingAs($this->admin())->post(route('admin.pricing.promotions.store'), [
            'title' => 'Juvo basket 15%',
            'type' => 'percent',
            'value' => 15,
            'scope' => 'vendor',
            'vendor_id' => $vendor->id,
            'funding_source' => Promotion::FUNDING_JUVO,
            'stack_group' => 'basket',
            'priority' => 5,
            'budget_amount' => 5000,
            'coupon_compatible' => 1,
        ])->assertRedirect();

        $promotion = Promotion::latest('id')->first();
        $this->assertSame('vendor', $promotion->scope);
        $this->assertSame('basket', $promotion->stack_group);
        $this->assertSame(Promotion::FUNDING_JUVO, $promotion->funding_source);
        $this->assertNull($promotion->product_id);
    }

    public function test_checkout_persists_pricing_breakdown_and_redemption(): void
    {
        $vendor = $this->vendor();
        [$product, $variant] = $this->product($vendor, 1000);

        Promotion::create([
            'title' => 'Juvo 10%', 'type' => 'percent', 'value' => 10, 'scope' => 'order',
            'status' => 'active', 'active' => true, 'funding_source' => Promotion::FUNDING_JUVO,
        ]);

        $lines = [[
            'product' => $product,
            'variant' => $variant,
            'quantity' => 1,
            'unit_price' => 1000.0,
        ]];

        $orders = app(OrderService::class)->createOrders($lines, [], null, [
            'customer_id' => null,
            'guest_email' => 'guest@example.com',
            'is_guest' => true,
            'payment_method' => 'cod',
            'status' => 'pending',
            'region' => null,
            'notify' => false,
        ]);

        $order = $orders->first();
        $this->assertNotNull($order->pricing_breakdown);
        $this->assertSame(100.0, (float) $order->juvo_funded_discount);
        $this->assertDatabaseHas('promotion_redemptions', ['order_id' => $order->id]);
    }
}
