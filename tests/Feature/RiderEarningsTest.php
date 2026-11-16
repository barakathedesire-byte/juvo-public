<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Rider\Rider;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RiderEarningsTest extends TestCase
{
    use DatabaseTransactions;

    protected function rider(): Rider
    {
        return Rider::create([
            'first_name' => 'Earn',
            'last_name' => 'Rider',
            'email' => 'earn-'.uniqid().'@example.com',
            'phone' => '071'.random_int(1000000, 9999999),
            'password' => 'password',
            'national_id' => 'NID-'.uniqid(),
            'date_of_birth' => '1995-01-01',
            'address' => '1 Rider Street',
            'city' => 'Dar es Salaam',
            'state' => 'Dar es Salaam',
            'license_number' => 'LIC-'.uniqid(),
            'license_expiry' => now()->addYear()->toDateString(),
            'kyc_status' => 'approved',
            'is_active' => true,
            'background_check_status' => 'clear',
        ]);
    }

    protected function deliveredOrder(Rider $rider, float $fee, Carbon $deliveredAt, array $overrides = []): Order
    {
        return Order::create(array_merge([
            'guest_email' => 'guest-'.uniqid().'@example.com',
            'total_amount' => 20000,
            'subtotal' => 20000,
            'delivery_fee' => $fee,
            'payment_status' => 'pending',
            'payment_method' => 'cod',
            'currency' => 'TZS',
            'status' => 'pending',
            'fulfillment_type' => 'delivery',
            'assigned_driver_id' => $rider->id,
            'driver_name' => $rider->full_name,
            'logistics_status' => 'delivered',
            'logistics_tracking_id' => 'TRK-'.uniqid(),
            'delivered_at' => $deliveredAt,
        ], $overrides));
    }

    public function test_earnings_page_shows_real_delivered_earnings(): void
    {
        $rider = $this->rider();
        $order = $this->deliveredOrder($rider, 4500, now());

        $this->actingAs($rider, 'rider')
            ->get(route('rider.earnings'))
            ->assertOk()
            ->assertSee('My Earnings')
            ->assertSee($order->logistics_tracking_id)
            ->assertSee('4,500');
    }

    public function test_custom_date_filter_updates_totals_and_transactions(): void
    {
        $rider = $this->rider();
        $todayOrder = $this->deliveredOrder($rider, 3000, now());
        $oldOrder = $this->deliveredOrder($rider, 9000, now()->subDays(45));

        $this->actingAs($rider, 'rider')
            ->get(route('rider.earnings', [
                'range' => 'custom',
                'from' => now()->subDays(2)->toDateString(),
                'to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee($todayOrder->logistics_tracking_id)
            ->assertDontSee($oldOrder->logistics_tracking_id);
    }

    public function test_pending_earnings_exclude_cod_collections(): void
    {
        $rider = $this->rider();
        $this->deliveredOrder($rider, 5000, now(), ['payment_method' => 'cod', 'cod_amount' => 20000, 'cod_collected_at' => now()]);

        $active = Order::create([
            'guest_email' => 'guest-'.uniqid().'@example.com',
            'total_amount' => 10000,
            'subtotal' => 10000,
            'delivery_fee' => 1200,
            'payment_status' => 'pending',
            'payment_method' => 'cod',
            'currency' => 'TZS',
            'status' => 'pending',
            'fulfillment_type' => 'delivery',
            'assigned_driver_id' => $rider->id,
            'driver_name' => $rider->full_name,
            'logistics_status' => 'accepted',
        ]);

        $response = $this->actingAs($rider, 'rider')->get(route('rider.earnings'));
        $response->assertOk()->assertSee('5,000');

        $this->assertSame(1200.0, (float) Order::where('assigned_driver_id', $rider->id)->whereIn('logistics_status', ['assigned', 'accepted', 'picked_up', 'in_transit'])->sum('delivery_fee'));
    }

    public function test_other_rider_earnings_are_not_visible(): void
    {
        $owner = $this->rider();
        $intruder = $this->rider();
        $order = $this->deliveredOrder($owner, 7000, now());

        $this->actingAs($intruder, 'rider')
            ->get(route('rider.earnings'))
            ->assertOk()
            ->assertDontSee($order->logistics_tracking_id);
    }

    public function test_payout_type_filter_shows_empty_state(): void
    {
        $rider = $this->rider();
        $this->deliveredOrder($rider, 2500, now());

        $this->actingAs($rider, 'rider')
            ->get(route('rider.earnings', ['type' => 'payouts']))
            ->assertOk()
            ->assertSee('No transactions in this period')
            ->assertSee('Payouts are not recorded');
    }
}
