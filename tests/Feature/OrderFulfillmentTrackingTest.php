<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Location;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Region;
use App\Models\Vendor;
use App\Services\Vendor\OrderWorkflowService;
use App\Support\Orders\OrderProgress;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OrderFulfillmentTrackingTest extends TestCase
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

    protected function region(): Region
    {
        return Region::firstOrCreate(
            ['name' => 'Dar es Salaam'],
            ['country' => 'Tanzania', 'is_active' => true, 'sort_order' => 0]
        );
    }

    protected function pickupLocation(string $name = 'Kariakoo Pickup Point', float $fee = 1500): Location
    {
        return Location::create([
            'region_id' => $this->region()->id,
            'name' => $name,
            'shipping_fee' => 3000,
            'pickup_fee' => $fee,
            'pickup_eta' => '2 hours',
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    protected function verifiedCustomer(): Customer
    {
        $customer = Customer::create([
            'name' => 'Fulfillment Buyer',
            'email' => 'fulfillment-'.uniqid().'@example.com',
            'password' => 'password123',
            'status' => 1,
        ]);
        $customer->forceFill(['email_verified_at' => now()])->save();

        return $customer;
    }

    protected function deliveryPayload(Customer $customer, array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Fulfillment',
            'last_name' => 'Buyer',
            'address' => '1 Track St',
            'region' => 'Dar es Salaam',
            'city' => 'Dar es Salaam',
            'email' => $customer->email,
            'phone' => '0700000000',
            'fulfillment_type' => 'delivery',
            'gateway' => 'payondelivery',
        ], $overrides);
    }

    protected function makeOrder(Vendor $vendor, array $attributes = []): Order
    {
        return Order::create(array_merge([
            'vendor_id' => $vendor->id,
            'guest_email' => 'guest-'.uniqid().'@example.com',
            'total_amount' => 20000,
            'subtotal' => 20000,
            'delivery_fee' => 0,
            'payment_status' => 'pending',
            'payment_method' => 'cod',
            'currency' => 'TZS',
            'status' => 'pending',
            'fulfillment_type' => 'delivery',
        ], $attributes));
    }

    public function test_pickup_checkout_stores_the_station_and_its_fee(): void
    {
        $location = $this->pickupLocation();
        $variant = $this->variantFor($this->activeVendor());
        $customer = $this->verifiedCustomer();

        $this->actingAs($customer, 'customer')
            ->withSession(['cart' => $this->cartFor($variant)])
            ->post(route('checkout.store'), [
                'first_name' => 'Pickup',
                'last_name' => 'Buyer',
                'region' => 'Dar es Salaam',
                'email' => $customer->email,
                'phone' => '0700000000',
                'fulfillment_type' => 'pickup',
                'pickup_location_id' => $location->id,
                'gateway' => 'payondelivery',
            ])
            ->assertRedirect(route('thankyou'));

        $order = Order::where('customer_id', $customer->id)->latest('id')->firstOrFail();

        $this->assertSame('pickup', $order->fulfillment_type);
        $this->assertSame($location->id, $order->pickup_location_id);
        $this->assertSame($location->name, $order->pickup_location_name);
        $this->assertSame(1500.0, (float) $order->delivery_fee);
    }

    public function test_delivery_checkout_still_requires_a_street_address(): void
    {
        $variant = $this->variantFor($this->activeVendor());
        $customer = $this->verifiedCustomer();

        $this->actingAs($customer, 'customer')
            ->withSession(['cart' => $this->cartFor($variant)])
            ->from(route('checkout.index'))
            ->post(route('checkout.store'), $this->deliveryPayload($customer, [
                'address' => '',
                'city' => '',
            ]))
            ->assertSessionHasErrors(['address', 'city']);
    }

    public function test_pickup_checkout_rejects_a_station_from_another_region(): void
    {
        $location = $this->pickupLocation();
        $variant = $this->variantFor($this->activeVendor());
        $customer = $this->verifiedCustomer();

        $this->actingAs($customer, 'customer')
            ->withSession(['cart' => $this->cartFor($variant)])
            ->from(route('checkout.index'))
            ->post(route('checkout.store'), [
                'first_name' => 'Pickup',
                'region' => 'Mwanza',
                'email' => $customer->email,
                'phone' => '0700000000',
                'fulfillment_type' => 'pickup',
                'pickup_location_id' => $location->id,
                'gateway' => 'payondelivery',
            ])
            ->assertSessionHasErrors(['pickup_location_id']);
    }

    public function test_pickup_progress_uses_the_pickup_journey(): void
    {
        $order = $this->makeOrder($this->activeVendor(), [
            'fulfillment_type' => 'pickup',
            'pickup_location_name' => 'Kariakoo Pickup Point',
        ]);

        $order->update(['status' => 'ready_for_pickup']);

        $milestones = OrderProgress::milestones($order->refresh());
        $labels = array_column($milestones, 'label');

        $this->assertContains('Ready for pickup', $labels);
        $this->assertContains('Collected', $labels);
        $this->assertNotContains('Rider arrived', $labels);
    }

    public function test_delivery_progress_uses_the_door_delivery_journey(): void
    {
        $order = $this->makeOrder($this->activeVendor());

        $order->update(['status' => 'rider_arrived']);

        $milestones = OrderProgress::milestones($order->refresh());
        $labels = array_column($milestones, 'label');

        $this->assertContains('Rider arrived', $labels);
        $this->assertContains('Delivered', $labels);
        $this->assertNotContains('Ready for pickup', $labels);
    }

    public function test_transition_records_fulfillment_timestamps(): void
    {
        $order = $this->makeOrder($this->activeVendor(), ['fulfillment_type' => 'pickup']);
        $workflow = app(OrderWorkflowService::class);

        $workflow->transition($order, 'accepted');
        $workflow->transition($order->refresh(), 'processing');
        $workflow->transition($order->refresh(), 'packed');
        $workflow->transition($order->refresh(), 'ready_for_pickup');

        $order->refresh();

        $this->assertSame('ready_for_pickup', $order->status);
        $this->assertNotNull($order->pickup_ready_at);
        $this->assertNotNull($order->fulfilled_at);
    }

    public function test_customer_sees_the_pickup_journey_on_the_order_page(): void
    {
        $customer = $this->verifiedCustomer();
        $order = $this->makeOrder($this->activeVendor(), [
            'customer_id' => $customer->id,
            'fulfillment_type' => 'pickup',
            'pickup_location_name' => 'Kariakoo Pickup Point',
            'status' => 'ready_for_pickup',
        ]);

        $this->actingAs($customer, 'customer')
            ->get(route('customer.orders.show', $order))
            ->assertOk()
            ->assertSee('Pickup station')
            ->assertSee('Kariakoo Pickup Point')
            ->assertSee('Ready for pickup')
            ->assertDontSee('Rider arrived');
    }
}
