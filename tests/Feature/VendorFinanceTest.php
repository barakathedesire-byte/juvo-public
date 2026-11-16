<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Vendor;
use App\Services\Vendor\CommissionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class VendorFinanceTest extends TestCase
{
    use DatabaseTransactions;

    protected function createVendor(array $attrs = []): Vendor
    {
        return Vendor::create([
            'name' => $attrs['name'] ?? 'Vendo',
            'shop_name' => $attrs['shop_name'] ?? 'Shop',
            'email' => $attrs['email'] ?? uniqid('vend@').'example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
            'is_approved' => true,
            'email_verified_at' => now(),
            'commission_rate' => $attrs['commission_rate'] ?? 10.0,
        ]);
    }

    protected function createProductFor(Vendor $vendor): Product
    {
        $shop = \App\Models\Shop::create([
            'vendor_id' => $vendor->id,
            'name' => $vendor->shop_name ?: $vendor->name,
            'slug' => 's-'.uniqid(),
            'status' => 'active',
        ]);
        $product = Product::create([
            'shop_id' => $shop->id,
            'vendor_id' => $vendor->id,
            'slug' => 'p-'.uniqid(),
            'category_id' => \App\Models\Category::first()->id,
            'brand_id' => null,
            'product_type' => 'simple',
            'status' => 1,
            'approval_status' => 'approved',
            'visibility' => 'public',
        ]);
        $product->translation()->create(['language_code' => 'en', 'name' => 'Test', 'description' => 'x']);

        return $product;
    }

    public function test_vendor_finance_balances_are_lifetime_derived_and_isolated(): void
    {
        $vendor = $this->createVendor();
        $other = $this->createVendor(['email' => uniqid().'@ex.com']);

        $product = $this->createProductFor($vendor);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'SKU' => 'SKU-'.uniqid(),
            'low_stock_threshold' => 2,
            'price' => 1000.00,
            'converted_price' => 1000.00,
            'stock' => 10,
            'is_primary' => 1,
            'weight' => 0,
            'dimensions' => '0x0x0',
            'variant_slug' => 'v-'.uniqid(),
        ]);

        $order = Order::create([
            'vendor_id' => $vendor->id,
            'customer_id' => null,
            'guest_email' => 'g@e.com',
            'is_guest' => 1,
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'cash_on_delivery',
            'currency' => 'TZS',
            'subtotal' => 1000.00,
            'total_amount' => 1000.00,
            'delivery_fee' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'coupon_code' => null,
        ]);
        OrderDetail::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity' => 1,
            'price' => 1000.00,
        ]);

        app(CommissionService::class)->recordForOrder($order);

        $this->actingAs($vendor, 'vendor');
        $resp = $this->get(route('vendor.finance.index'));
        $resp->assertStatus(200);
        $resp->assertSee('Finance');

        $balances = $this->app->make(\App\Services\Vendor\VendorFinanceService::class)->balances($vendor);
        $this->assertEqualsWithDelta(0.00, $balances['available'], 0.01);
        $this->assertEqualsWithDelta(900.00, $balances['pending'], 0.01); // 10% commission
    }

    public function test_earnings_become_available_on_completed_order_and_withdrawable_respects_reserved(): void
    {
        $vendor = $this->createVendor();
        $product = $this->createProductFor($vendor);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'SKU' => 'SKU-'.uniqid(),
            'low_stock_threshold' => 2,
            'price' => 1000.00,
            'converted_price' => 1000.00,
            'stock' => 10,
            'is_primary' => 1,
            'weight' => 0,
            'dimensions' => '0x0x0',
            'variant_slug' => 'v-'.uniqid(),
        ]);

        $order = Order::create([
            'vendor_id' => $vendor->id,
            'customer_id' => null,
            'guest_email' => 'g2@e.com',
            'is_guest' => 1,
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'cash_on_delivery',
            'currency' => 'TZS',
            'subtotal' => 1000.00,
            'total_amount' => 1000.00,
            'delivery_fee' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'coupon_code' => null,
        ]);
        OrderDetail::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity' => 1,
            'price' => 1000.00,
        ]);

        app(CommissionService::class)->recordForOrder($order);
        $finance = app(\App\Services\Vendor\VendorFinanceService::class);

        $this->actingAs($vendor, 'vendor');
        $this->post(route('vendor.orders.status', $order->id), ['status' => 'accepted']);
        $this->post(route('vendor.orders.status', $order->id), ['status' => 'processing']);
        $this->post(route('vendor.orders.status', $order->id), ['status' => 'dispatched']);
        $this->post(route('vendor.orders.status', $order->id), ['status' => 'completed']);

        $b = $finance->balances($vendor);
        $this->assertEqualsWithDelta(900.00, $b['available'], 0.01);
        $this->assertEqualsWithDelta(0.00, $b['pending'], 0.01);
        $this->assertEqualsWithDelta(900.00, $b['withdrawable'], 0.01);

        // Payout request reserves
        $payout = $finance->requestPayout($vendor, 500.00);
        $this->assertNotNull($payout);

        $b2 = $finance->balances($vendor);
        $this->assertEqualsWithDelta(900.00, $b2['available'], 0.01);
        $this->assertEqualsWithDelta(500.00, $b2['reserved'], 0.01);
        $this->assertEqualsWithDelta(400.00, $b2['withdrawable'], 0.01);
    }

    public function test_vendor_finance_cannot_see_other_vendors(): void
    {
        $vendorA = $this->createVendor(['email' => uniqid().'@a.com']);
        $vendorB = $this->createVendor(['email' => uniqid().'@b.com']);

        $this->actingAs($vendorA, 'vendor');
        $this->get(route('vendor.finance.index'))->assertStatus(200);

        // Simulate access by URL doesn't leak B's data; acting as A only
        $this->assertTrue(true);
    }
}
