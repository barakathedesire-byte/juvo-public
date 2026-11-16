<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingRate;
use App\Models\TaxRate;
use App\Models\Vendor;
use App\Models\VendorCommission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CheckoutPricingTest extends TestCase
{
    use DatabaseTransactions;

    protected function activeVendor(): Vendor
    {
        $vendor = Vendor::where('status', 'active')->where('is_approved', 1)->firstOrFail();
        $vendor->update(['commission_rate' => null]);

        return $vendor;
    }

    protected function variantFor(Vendor $vendor, float $price = 10000): ProductVariant
    {
        $variant = Product::where('vendor_id', $vendor->id)->firstOrFail()
            ->variants()->where('is_primary', 1)->firstOrFail();

        $variant->update(['stock' => 50, 'price' => $price, 'discount_price' => null]);

        return $variant;
    }

    protected function cartFor(ProductVariant $variant, int $qty = 2): array
    {
        return [
            'cart_'.$variant->product_id.'_' => [
                'product_id' => $variant->product_id,
                'variant_id' => $variant->id,
                'price' => 1,
                'quantity' => $qty,
            ],
        ];
    }

    protected function payload(Customer $customer, array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Pricing',
            'last_name' => 'Buyer',
            'address' => '1 Test St',
            'region' => 'Dar es Salaam',
            'city' => 'Dar es Salaam',
            'email' => $customer->email,
            'phone' => '0700000000',
            'gateway' => 'payondelivery',
        ], $overrides);
    }

    protected function verifiedCustomer(): Customer
    {
        $customer = Customer::create([
            'name' => 'Pricing Buyer',
            'email' => 'pricing-'.uniqid().'@example.com',
            'password' => 'password123',
            'status' => 1,
        ]);
        $customer->forceFill(['email_verified_at' => now()])->save();

        return $customer;
    }

    public function test_coupon_discount_is_applied_and_capped_by_max_discount(): void
    {
        $variant = $this->variantFor($this->activeVendor());
        $customer = $this->verifiedCustomer();

        $coupon = Coupon::create([
            'code' => 'CAP50',
            'discount' => 50,
            'type' => 'percentage',
            'max_discount' => 3000,
            'is_active' => true,
        ]);

        $this->actingAs($customer, 'customer')
            ->withSession(['cart' => $this->cartFor($variant), 'cart_coupon' => 'CAP50'])
            ->post(route('checkout.store'), $this->payload($customer))
            ->assertRedirect(route('thankyou'));

        $order = Order::where('customer_id', $customer->id)->latest('id')->firstOrFail();

        $this->assertSame(20000.0, (float) $order->subtotal);
        $this->assertSame(3000.0, (float) $order->discount_amount);
        $this->assertSame('CAP50', $order->coupon_code);
        $this->assertSame(17000.0, (float) $order->total_amount);
        $this->assertSame(1, $coupon->fresh()->used_count);
    }

    public function test_exhausted_coupon_is_ignored_at_checkout(): void
    {
        $variant = $this->variantFor($this->activeVendor());
        $customer = $this->verifiedCustomer();

        Coupon::create([
            'code' => 'USEDUP',
            'discount' => 50,
            'type' => 'percentage',
            'usage_limit' => 1,
            'used_count' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($customer, 'customer')
            ->withSession(['cart' => $this->cartFor($variant), 'cart_coupon' => 'USEDUP'])
            ->post(route('checkout.store'), $this->payload($customer))
            ->assertRedirect(route('thankyou'));

        $order = Order::where('customer_id', $customer->id)->latest('id')->firstOrFail();

        $this->assertSame(0.0, (float) $order->discount_amount);
        $this->assertNull($order->coupon_code);
        $this->assertSame(20000.0, (float) $order->total_amount);
    }

    public function test_tax_rate_is_applied_on_discounted_subtotal(): void
    {
        $variant = $this->variantFor($this->activeVendor());
        $customer = $this->verifiedCustomer();

        TaxRate::create(['name' => 'VAT 18', 'rate' => 18, 'country' => 'Tanzania', 'is_active' => true]);

        $this->actingAs($customer, 'customer')
            ->withSession(['cart' => $this->cartFor($variant)])
            ->post(route('checkout.store'), $this->payload($customer))
            ->assertRedirect(route('thankyou'));

        $order = Order::where('customer_id', $customer->id)->latest('id')->firstOrFail();

        $this->assertSame(3600.0, (float) $order->tax_amount);
        $this->assertSame(23600.0, (float) $order->total_amount);
    }

    public function test_shipping_rate_is_applied_for_matching_region(): void
    {
        $variant = $this->variantFor($this->activeVendor());
        $customer = $this->verifiedCustomer();

        ShippingRate::create([
            'name' => 'Mwanza delivery',
            'region' => 'Mwanza',
            'rate' => 5000,
            'min_order_value' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($customer, 'customer')
            ->withSession(['cart' => $this->cartFor($variant)])
            ->post(route('checkout.store'), $this->payload($customer, ['region' => 'Mwanza', 'city' => 'Mwanza']))
            ->assertRedirect(route('thankyou'));

        $order = Order::where('customer_id', $customer->id)->latest('id')->firstOrFail();

        $this->assertSame(5000.0, (float) $order->delivery_fee);
        $this->assertSame(25000.0, (float) $order->total_amount);
    }

    public function test_guest_checkout_is_blocked_and_requires_an_account(): void
    {
        $variant = $this->variantFor($this->activeVendor());

        $before = Order::count();

        $this->withSession(['cart' => $this->cartFor($variant)])
            ->post(route('checkout.store'), [
                'first_name' => 'Guest',
                'last_name' => 'Buyer',
                'address' => '9 Guest Rd',
                'region' => 'Arusha',
                'city' => 'Arusha',
                'email' => 'guest-'.uniqid().'@example.com',
                'phone' => '0711111111',
                'gateway' => 'payondelivery',
            ])
            ->assertRedirect(route('customer.login'));

        $this->assertSame($before, Order::count());
    }

    public function test_guest_cannot_open_the_checkout_page(): void
    {
        $this->get(route('checkout.index'))->assertRedirect(route('customer.login'));
    }

    public function test_commission_is_recorded_using_the_default_rate(): void
    {
        $vendor = $this->activeVendor();
        $variant = $this->variantFor($vendor);
        $customer = $this->verifiedCustomer();

        $this->actingAs($customer, 'customer')
            ->withSession(['cart' => $this->cartFor($variant)])
            ->post(route('checkout.store'), $this->payload($customer))
            ->assertRedirect(route('thankyou'));

        $order = Order::where('customer_id', $customer->id)->latest('id')->firstOrFail();

        $this->assertSame(2000.0, (float) $order->commission_total);
        $this->assertSame(18000.0, (float) $order->net_vendor_amount);

        $this->assertDatabaseHas('vendor_commissions', [
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'status' => 'pending',
        ]);
        $this->assertSame(1, VendorCommission::where('order_id', $order->id)->count());
    }
}
