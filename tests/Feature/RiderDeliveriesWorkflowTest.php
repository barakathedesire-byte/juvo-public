<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Rider\Rider;
use App\Models\ShippingAddress;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RiderDeliveriesWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    protected function rider(): Rider
    {
        $rider = Rider::create([
            'first_name' => 'Test',
            'last_name' => 'Rider',
            'email' => 'rider-'.uniqid().'@example.com',
            'phone' => '070'.random_int(1000000, 9999999),
            'national_id' => 'NID-'.uniqid(),
            'date_of_birth' => '1995-01-01',
            'address' => '1 Rider Street',
            'city' => 'Dar es Salaam',
            'state' => 'Dar es Salaam',
            'license_number' => 'LIC-'.uniqid(),
            'license_expiry' => now()->addYear()->toDateString(),
            'password' => 'password',
            'kyc_status' => 'approved',
            'is_active' => true,
            'background_check_status' => 'clear',
            'current_lat' => -6.816,
            'current_lng' => 39.280,
        ]);

        return $rider;
    }

    protected function orderFor(Rider $rider, array $overrides = []): Order
    {
        $order = Order::create(array_merge([
            'vendor_id' => null,
            'guest_email' => 'guest-'.uniqid().'@example.com',
            'total_amount' => 25000,
            'subtotal' => 25000,
            'delivery_fee' => 3000,
            'payment_status' => 'pending',
            'payment_method' => 'cod',
            'currency' => 'TZS',
            'status' => 'pending',
            'fulfillment_type' => 'delivery',
            'assigned_driver_id' => $rider->id,
            'driver_name' => $rider->full_name,
            'driver_phone' => '0700000000',
            'assigned_at' => now(),
            'logistics_status' => 'assigned',
            'logistics_tracking_id' => 'TRK-'.uniqid(),
        ], $overrides));

        ShippingAddress::create([
            'order_id' => $order->id,
            'name' => 'Customer Receiver',
            'phone' => '0711111111',
            'address' => '123 Delivery Road',
            'city' => 'Dar es Salaam',
            'state' => 'Dar es Salaam',
            'postal_code' => '11101',
            'country' => 'Tanzania',
        ]);

        return $order->fresh(['shippingAddress']);
    }

    public function test_deliveries_list_loads_for_assigned_rider(): void
    {
        $rider = $this->rider();
        $this->orderFor($rider);

        $this->actingAs($rider, 'rider')
            ->get(route('rider.orders'))
            ->assertOk()
            ->assertSee('Deliveries')
            ->assertSee('New request');

        $this->actingAs($rider, 'rider')
            ->getJson(route('rider.orders').'?filter=new&q=Customer')
            ->assertOk()
            ->assertJsonStructure(['html', 'total', 'summary', 'filter', 'query']);
    }

    public function test_complete_delivery_journey_updates_status_and_proof(): void
    {
        $rider = $this->rider();
        $order = $this->orderFor($rider);

        $this->actingAs($rider, 'rider');

        $this->get(route('rider.deliveries.show', $order))->assertOk()->assertSee('Delivery #'.$order->id);

        $this->postJson(route('rider.deliveries.accept', $order))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'accepted');

        $this->postJson(route('rider.deliveries.arrived-pickup', $order))
            ->assertOk()
            ->assertJsonPath('status', 'accepted');

        $this->postJson(route('rider.deliveries.pickup', $order))
            ->assertOk()
            ->assertJsonPath('status', 'picked_up');

        $this->postJson(route('rider.deliveries.transit', $order))
            ->assertOk()
            ->assertJsonPath('status', 'in_transit');

        $this->postJson(route('rider.deliveries.arrived-dropoff', $order))
            ->assertOk()
            ->assertJsonPath('status', 'in_transit');

        $this->post(route('rider.deliveries.deliver', $order), [
            'receiver_name' => 'Customer Receiver',
            'notes' => 'Left at gate',
            'cod_collected' => '1',
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $order->refresh();
        $this->assertSame('delivered', $order->logistics_status);
        $this->assertSame('Customer Receiver', $order->pod_receiver_name);
        $this->assertSame('Left at gate', $order->pod_notes);
        $this->assertNotNull($order->pod_delivered_at);
        $this->assertNotNull($order->cod_collected_at);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame(1, $order->deliveryEvents()->where('type', 'arrived_pickup')->count());
        $this->assertSame(1, $order->deliveryEvents()->where('type', 'arrived_dropoff')->count());
    }

    public function test_duplicate_accept_is_rejected(): void
    {
        $rider = $this->rider();
        $order = $this->orderFor($rider);

        $this->actingAs($rider, 'rider');
        $this->postJson(route('rider.deliveries.accept', $order))->assertOk();

        $this->postJson(route('rider.deliveries.accept', $order))
            ->assertStatus(422)
            ->assertJsonPath('errors.status.0', 'This action is not available while the delivery is "Rider accepted".');
    }

    public function test_invalid_transition_is_rejected(): void
    {
        $rider = $this->rider();
        $order = $this->orderFor($rider, ['logistics_status' => 'assigned']);

        $this->actingAs($rider, 'rider');

        $this->postJson(route('rider.deliveries.transit', $order))
            ->assertStatus(422);
    }

    public function test_another_rider_cannot_open_delivery(): void
    {
        $owner = $this->rider();
        $intruder = $this->rider();
        $order = $this->orderFor($owner);

        $this->actingAs($intruder, 'rider')
            ->get(route('rider.deliveries.show', $order))
            ->assertNotFound();
    }

    public function test_another_rider_cannot_complete_or_fail_assigned_delivery(): void
    {
        $owner = $this->rider();
        $intruder = $this->rider();
        $order = $this->orderFor($owner);

        $this->actingAs($intruder, 'rider');

        $this->postJson(route('rider.deliveries.deliver', $order), [
            'receiver_name' => 'Customer Receiver',
            'cod_collected' => '1',
        ])->assertStatus(422)->assertJsonPath('errors.rider.0', 'This delivery is not assigned to you.');

        $this->postJson(route('rider.deliveries.fail', $order), [
            'reason' => 'no_answer',
            'note' => 'Attempted by the wrong rider',
        ])->assertStatus(422)->assertJsonPath('errors.rider.0', 'This delivery is not assigned to you.');

        $order->refresh();
        $this->assertSame('assigned', $order->logistics_status);
        $this->assertSame($owner->id, $order->assigned_driver_id);
        $this->assertSame(0, $order->deliveryEvents()->count());
    }

    public function test_failure_report_stores_reason(): void
    {
        $rider = $this->rider();
        $order = $this->orderFor($rider);

        $this->actingAs($rider, 'rider');
        $this->postJson(route('rider.deliveries.accept', $order))->assertOk();
        $this->postJson(route('rider.deliveries.pickup', $order))->assertOk();
        $this->postJson(route('rider.deliveries.transit', $order))->assertOk();

        $this->postJson(route('rider.deliveries.fail', $order), [
            'reason' => 'no_answer',
            'note' => 'No one answered the door',
        ])->assertOk()->assertJsonPath('success', true);

        $order->refresh();
        $this->assertSame('failed', $order->logistics_status);
        $this->assertSame('no_answer', $order->failed_reason);
        $this->assertSame('No one answered the door', $order->failure_note);
    }
}
