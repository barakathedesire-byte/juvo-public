<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OrderTrackingTest extends TestCase
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

    protected function cartFor(ProductVariant $variant, int $qty = 1): array
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

    protected function payload(Customer $customer): array
    {
        return [
            'first_name' => 'Track',
            'last_name' => 'Buyer',
            'address' => '1 Track St',
            'region' => 'Dar es Salaam',
            'city' => 'Dar es Salaam',
            'email' => $customer->email,
            'phone' => '0700000000',
            'gateway' => 'payondelivery',
        ];
    }

    protected function verifiedCustomer(): Customer
    {
        $customer = Customer::create([
            'name' => 'Track Buyer',
            'email' => 'track-'.uniqid().'@example.com',
            'password' => 'password123',
            'status' => 1,
        ]);
        $customer->forceFill(['email_verified_at' => now()])->save();

        return $customer;
    }

    public function test_thankyou_page_shows_the_placed_order_and_tracking(): void
    {
        $variant = $this->variantFor($this->activeVendor());
        $customer = $this->verifiedCustomer();

        $this->actingAs($customer, 'customer')
            ->withSession(['cart' => $this->cartFor($variant)])
            ->post(route('checkout.store'), $this->payload($customer))
            ->assertRedirect(route('thankyou'));

        $order = \App\Models\Order::where('customer_id', $customer->id)->latest('id')->firstOrFail();

        $this->actingAs($customer, 'customer')
            ->get(route('thankyou'))
            ->assertOk()
            ->assertSee('Order #'.$order->id)
            ->assertSee('Tracking');
    }

    public function test_customer_can_open_the_tracking_page_for_their_order(): void
    {
        $variant = $this->variantFor($this->activeVendor());
        $customer = $this->verifiedCustomer();

        $this->actingAs($customer, 'customer')
            ->withSession(['cart' => $this->cartFor($variant)])
            ->post(route('checkout.store'), $this->payload($customer));

        $order = \App\Models\Order::where('customer_id', $customer->id)->latest('id')->firstOrFail();

        $this->actingAs($customer, 'customer')
            ->get(route('customer.orders.show', $order))
            ->assertOk()
            ->assertSee('Order timeline');
    }

    public function test_guest_cannot_open_the_thankyou_page(): void
    {
        $this->get(route('thankyou'))->assertRedirect(route('customer.login'));
    }

    public function test_guest_can_track_an_order_by_number_and_email(): void
    {
        $variant = $this->variantFor($this->activeVendor());
        $customer = $this->verifiedCustomer();

        $this->actingAs($customer, 'customer')
            ->withSession(['cart' => $this->cartFor($variant)])
            ->post(route('checkout.store'), $this->payload($customer));

        $order = \App\Models\Order::where('customer_id', $customer->id)->latest('id')->firstOrFail();

        $this->post(route('support.track'), [
            'order_id' => $order->id,
            'email' => $customer->email,
        ])
            ->assertOk()
            ->assertSee('Order #'.$order->id);
    }
}
