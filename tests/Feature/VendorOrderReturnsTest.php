<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Refund;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class VendorOrderReturnsTest extends TestCase
{
    use DatabaseTransactions;

    protected function vendor(): Vendor
    {
        return Vendor::create([
            'name' => 'Order Vendor',
            'shop_name' => 'Order Shop',
            'email' => uniqid('ord').'@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
            'is_approved' => true,
            'email_verified_at' => now(),
        ]);
    }

    protected function order(Vendor $vendor, string $status): Order
    {
        return Order::create([
            'vendor_id' => $vendor->id,
            'customer_id' => null,
            'guest_email' => 'g@e.com',
            'is_guest' => 1,
            'status' => $status,
            'payment_status' => 'pending',
            'payment_method' => 'cash_on_delivery',
            'currency' => 'TZS',
            'subtotal' => 1000.00,
            'total_amount' => 1000.00,
            'delivery_fee' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
        ]);
    }

    protected function refundFor(Order $order, Vendor $vendor, string $status = 'requested'): Refund
    {
        $user = \App\Models\User::first();
        $gateway = \App\Models\PaymentGateway::first();

        $payment = \App\Models\Payment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'gateway_id' => $gateway->id,
            'amount' => 1000,
            'currency' => 'TZS',
            'status' => 'completed',
        ]);

        return Refund::create([
            'payment_id' => $payment->id,
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'amount' => 1000,
            'currency' => 'TZS',
            'status' => $status,
            'reason' => 'Defective item',
        ]);
    }

    public function test_orders_index_shows_tab_counts(): void
    {
        $vendor = $this->vendor();
        $this->order($vendor, 'pending');
        $this->order($vendor, 'processing');
        $this->order($vendor, 'dispatched');

        $response = $this->actingAs($vendor, 'vendor')->get(route('vendor.orders.index'));
        $response->assertOk();
        $this->assertSame(3, \App\Models\Order::where('vendor_id', $vendor->id)->count());
    }

    public function test_order_tab_filter_returns_only_matching_status(): void
    {
        $vendor = $this->vendor();
        $pending = $this->order($vendor, 'pending');
        $this->order($vendor, 'dispatched');

        $response = $this->actingAs($vendor, 'vendor')
            ->postJson(route('vendor.orders.data'), ['status' => 'pending'])
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($pending->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_vendor_has_no_return_management_surface(): void
    {
        $vendor = $this->vendor();
        $order = $this->order($vendor, 'delivered');
        $refund = $this->refundFor($order, $vendor);

        $this->actingAs($vendor, 'vendor')
            ->get('/vendor/returns')
            ->assertNotFound();

        $this->actingAs($vendor, 'vendor')
            ->post('/vendor/returns/'.$refund->id.'/approve')
            ->assertNotFound();

        $this->assertSame('requested', $refund->fresh()->status);
    }
}
