<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\Vendor;
use App\Services\Store\CheckoutPricingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class VendorMarketingTest extends TestCase
{
    use DatabaseTransactions;

    protected function vendor(array $attrs = []): Vendor
    {
        return Vendor::create([
            'name' => $attrs['name'] ?? 'Marketer',
            'shop_name' => $attrs['shop_name'] ?? 'Marketing Shop',
            'email' => $attrs['email'] ?? uniqid('mkt').'@example.com',
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
            ['name' => $vendor->shop_name ?? 'Shop', 'slug' => 'shop-'.uniqid(), 'status' => 'active']
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
            'low_stock_threshold' => 2,
            'price' => $price,
            'stock' => 10,
            'is_primary' => 1,
            'variant_slug' => 'v-'.uniqid(),
        ]);

        return [$product->fresh(), $variant];
    }

    public function test_vendor_can_schedule_a_running_promotion(): void
    {
        $vendor = $this->vendor();
        [$product] = $this->product($vendor);

        $this->actingAs($vendor, 'vendor')
            ->post(route('vendor.promotions.store'), [
                'product_id' => $product->id,
                'title' => 'Weekend sale',
                'type' => 'percent',
                'value' => 20,
                'starts_at' => now()->subDay()->toDateTimeString(),
                'ends_at' => now()->addDay()->toDateTimeString(),
            ])
            ->assertRedirect(route('vendor.promotions.index'));

        $promotion = Promotion::where('product_id', $product->id)->firstOrFail();
        $this->assertSame('Weekend sale', $promotion->title);
        $this->assertTrue($promotion->isRunning());

        $variant = ProductVariant::where('product_id', $product->id)->where('is_primary', true)->first();
        $this->assertEqualsWithDelta(800.0, (float) $variant->discount_price, 0.01);
    }

    public function test_percentage_promotion_over_100_is_rejected(): void
    {
        $vendor = $this->vendor();
        [$product] = $this->product($vendor);

        $this->actingAs($vendor, 'vendor')
            ->post(route('vendor.promotions.store'), [
                'product_id' => $product->id,
                'type' => 'percent',
                'value' => 150,
            ])
            ->assertSessionHasErrors('value');
    }

    public function test_future_promotion_is_not_applied_until_it_starts(): void
    {
        $vendor = $this->vendor();
        [$product, $variant] = $this->product($vendor);

        Promotion::create([
            'vendor_id' => $vendor->id,
            'product_id' => $product->id,
            'type' => 'percent',
            'value' => 20,
            'active' => true,
            'starts_at' => now()->addDays(2),
        ]);

        $this->assertNull($variant->fresh()->discount_price);
    }

    public function test_vendor_coupon_is_created_scoped_and_uppercased(): void
    {
        $vendor = $this->vendor();

        $this->actingAs($vendor, 'vendor')
            ->post(route('vendor.coupons.store'), [
                'code' => 'launch10',
                'type' => 'percentage',
                'discount' => 10,
                'is_active' => 1,
            ])
            ->assertRedirect(route('vendor.coupons.index'));

        $coupon = Coupon::where('vendor_id', $vendor->id)->first();
        $this->assertNotNull($coupon);
        $this->assertSame('LAUNCH10', $coupon->code);
    }

    public function test_vendor_cannot_modify_another_vendors_coupon(): void
    {
        $vendorA = $this->vendor();
        $vendorB = $this->vendor(['email' => uniqid('b').'@example.com']);

        $coupon = Coupon::create([
            'vendor_id' => $vendorB->id,
            'code' => 'BCOUPON',
            'type' => 'fixed',
            'discount' => 500,
            'is_active' => 1,
        ]);

        $this->actingAs($vendorA, 'vendor')
            ->delete(route('vendor.coupons.destroy', $coupon->id))
            ->assertNotFound();

        $this->assertDatabaseHas('coupons', ['id' => $coupon->id]);
    }

    public function test_vendor_coupon_only_discounts_that_vendors_items(): void
    {
        $vendorA = $this->vendor();
        $vendorB = $this->vendor(['email' => uniqid('b2').'@example.com']);
        [$productA] = $this->product($vendorA, 1000);
        [$productB] = $this->product($vendorB, 1000);

        $coupon = Coupon::create([
            'vendor_id' => $vendorA->id,
            'code' => 'AONLY',
            'type' => 'fixed',
            'discount' => 200,
            'is_active' => 1,
        ]);

        $pricing = app(CheckoutPricingService::class);

        $mixed = $pricing->quote([
            ['unit_price' => 1000, 'quantity' => 1, 'product' => $productA],
            ['unit_price' => 1000, 'quantity' => 1, 'product' => $productB],
        ], $coupon);
        $this->assertEqualsWithDelta(200.0, $mixed['discount'], 0.01);

        $onlyB = $pricing->quote([
            ['unit_price' => 1000, 'quantity' => 1, 'product' => $productB],
        ], $coupon);
        $this->assertEqualsWithDelta(0.0, $onlyB['discount'], 0.01);
        $this->assertNotNull($onlyB['coupon_error']);
    }
}
