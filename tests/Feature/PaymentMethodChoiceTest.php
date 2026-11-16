<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Region;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PaymentMethodChoiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function variant(): ProductVariant
    {
        $vendor = Vendor::where('status', 'active')->where('is_approved', 1)->firstOrFail();
        $variant = Product::where('vendor_id', $vendor->id)->firstOrFail()
            ->variants()->where('is_primary', 1)->firstOrFail();
        $variant->update(['stock' => 10, 'price' => 10000, 'discount_price' => null]);

        return $variant;
    }

    protected function customer(): Customer
    {
        $customer = Customer::create([
            'name' => 'Pay Chooser',
            'email' => 'pay-chooser-'.uniqid().'@example.com',
            'password' => 'password123',
            'status' => 1,
        ]);
        $customer->forceFill(['email_verified_at' => now()])->save();

        return $customer;
    }

    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Pay',
            'last_name' => 'Chooser',
            'address' => '1 Test St',
            'region' => 'Mwanza',
            'city' => 'Mwanza',
            'email' => 'buyer@example.com',
            'phone' => '0700000000',
        ], $overrides);
    }

    protected function cartFor(ProductVariant $variant): array
    {
        return [
            'cart_'.$variant->product_id.'_' => [
                'product_id' => $variant->product_id,
                'variant_id' => $variant->id,
                'price' => 1,
                'quantity' => 1,
            ],
        ];
    }

    public function test_pay_on_delivery_is_normalised_and_never_paid_on_creation(): void
    {
        $variant = $this->variant();
        $customer = $this->customer();

        $this->actingAs($customer, 'customer')
            ->withSession(['cart' => $this->cartFor($variant)])
            ->post(route('checkout.store'), $this->payload([
                'payment_method_category' => 'pay_on_delivery',
                'gateway' => 'cod',
            ]))
            ->assertRedirect(route('thankyou'));

        $order = Order::where('customer_id', $customer->id)->latest('id')->firstOrFail();
        $this->assertSame('pending', $order->payment_status);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'payment_method' => 'payondelivery',
            'status' => 'pending',
        ]);
        $this->assertSame(0, Payment::where('order_id', $order->id)->where('status', 'completed')->count());
    }

    public function test_instant_pay_requires_an_active_gateway(): void
    {
        $variant = $this->variant();
        $customer = $this->customer();

        $this->actingAs($customer, 'customer')
            ->withSession(['cart' => $this->cartFor($variant)])
            ->post(route('checkout.store'), $this->payload([
                'payment_method_category' => 'instant_pay',
                'gateway' => 'not-a-real-gateway',
            ]))
            ->assertSessionHasErrors('gateway');

        $this->assertSame(0, Order::where('customer_id', $customer->id)->count());
    }

    public function test_instant_pay_rejects_inactive_gateway(): void
    {
        $variant = $this->variant();
        $customer = $this->customer();

        PaymentGateway::create([
            'name' => 'Disabled GW',
            'code' => 'disabled-gw',
            'is_active' => 0,
        ]);

        $this->actingAs($customer, 'customer')
            ->withSession(['cart' => $this->cartFor($variant)])
            ->post(route('checkout.store'), $this->payload([
                'payment_method_category' => 'instant_pay',
                'gateway' => 'disabled-gw',
            ]))
            ->assertSessionHasErrors('gateway');

        $this->assertSame(0, Order::where('customer_id', $customer->id)->count());
    }

    public function test_instant_pay_records_order_with_active_gateway(): void
    {
        $variant = $this->variant();
        $customer = $this->customer();

        PaymentGateway::create([
            'name' => 'Live GW',
            'code' => 'live-gw',
            'is_active' => 1,
        ]);

        $this->actingAs($customer, 'customer')
            ->withSession(['cart' => $this->cartFor($variant)])
            ->post(route('checkout.store'), $this->payload([
                'email' => $customer->email,
                'payment_method_category' => 'instant_pay',
                'gateway' => 'live-gw',
            ]))
            ->assertRedirect(route('thankyou'));

        $order = Order::where('customer_id', $customer->id)->latest('id')->firstOrFail();
        $this->assertSame('pending', $order->payment_status);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'payment_method' => 'live-gw',
            'status' => 'pending',
        ]);
    }

    public function test_pay_on_delivery_is_rejected_for_inactive_region(): void
    {
        $variant = $this->variant();
        $customer = $this->customer();

        Region::create([
            'name' => 'Dormant Zone',
            'country' => 'Tanzania',
            'is_active' => false,
            'sort_order' => 0,
        ]);

        $this->actingAs($customer, 'customer')
            ->withSession(['cart' => $this->cartFor($variant)])
            ->post(route('checkout.store'), $this->payload([
                'region' => 'Dormant Zone',
                'payment_method_category' => 'pay_on_delivery',
                'gateway' => 'cod',
            ]))
            ->assertSessionHasErrors('gateway');

        $this->assertSame(0, Order::where('customer_id', $customer->id)->count());
    }

    public function test_payment_intent_rejects_pod_and_unknown_gateways(): void
    {
        $variant = $this->variant();
        $customer = $this->customer();

        $this->actingAs($customer, 'customer')
            ->withSession(['cart' => $this->cartFor($variant)])
            ->post(route('checkout.process'), ['gateway' => 'cod'])
            ->assertStatus(422);

        $this->actingAs($customer, 'customer')
            ->withSession(['cart' => $this->cartFor($variant)])
            ->post(route('checkout.process'), ['gateway' => 'totally-unknown'])
            ->assertStatus(422);
    }
}
