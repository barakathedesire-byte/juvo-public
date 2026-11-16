<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class VendorAnalyticsTest extends TestCase
{
    use DatabaseTransactions;

    protected function vendor(): Vendor
    {
        return Vendor::create([
            'name' => 'Analyst',
            'shop_name' => 'Analytics Shop',
            'email' => uniqid('an').'@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
            'is_approved' => true,
            'email_verified_at' => now(),
        ]);
    }

    protected function completedOrder(Vendor $vendor): Order
    {
        $shop = \App\Models\Shop::create([
            'vendor_id' => $vendor->id,
            'name' => $vendor->shop_name ?? 'Shop',
            'slug' => 'shop-'.uniqid(),
            'status' => 'active',
        ]);

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
        $product->translation()->create(['language_code' => 'en', 'name' => 'Analytics Product', 'description' => 'd']);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'SKU' => 'SKU-'.uniqid(),
            'low_stock_threshold' => 2,
            'price' => 1000,
            'stock' => 10,
            'is_primary' => 1,
            'variant_slug' => 'v-'.uniqid(),
        ]);

        $order = Order::create([
            'vendor_id' => $vendor->id,
            'customer_id' => null,
            'guest_email' => 'g@e.com',
            'is_guest' => 1,
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'cash_on_delivery',
            'currency' => 'TZS',
            'subtotal' => 2000,
            'total_amount' => 2000,
            'delivery_fee' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
        ]);

        OrderDetail::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity' => 2,
            'price' => 1000,
        ]);

        return $order;
    }

    public function test_analytics_page_reports_vendor_sales(): void
    {
        $vendor = $this->vendor();
        $this->completedOrder($vendor);

        $response = $this->actingAs($vendor, 'vendor')->get(route('vendor.analytics.index'));
        $response->assertOk();
        $response->assertSee('Analytics');
        $response->assertSee('Analytics Product');
    }

    public function test_analytics_export_returns_csv(): void
    {
        $vendor = $this->vendor();
        $this->completedOrder($vendor);

        $response = $this->actingAs($vendor, 'vendor')->get(route('vendor.analytics.export'));
        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('Daily sales', $response->streamedContent());
    }

    public function test_analytics_is_isolated_between_vendors(): void
    {
        $vendorA = $this->vendor();
        $vendorB = $this->vendor();
        $this->completedOrder($vendorB);

        $response = $this->actingAs($vendorA, 'vendor')->get(route('vendor.analytics.index'));
        $response->assertOk();
        $response->assertDontSee('Analytics Product');
    }
}
