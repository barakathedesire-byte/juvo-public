<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderStatusHistory;
use App\Models\ProductVariant;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrderWorkspaceTest extends TestCase
{
    use DatabaseTransactions;

    protected function vendor(): Vendor
    {
        return Vendor::where('status', 'active')->where('is_approved', 1)->firstOrFail();
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
        ], $attributes));
    }

    protected function variant(Vendor $vendor): ProductVariant
    {
        return ProductVariant::whereHas('product', fn ($q) => $q->where('vendor_id', $vendor->id))->firstOrFail();
    }

    public function test_vendor_can_view_own_order_detail(): void
    {
        $vendor = $this->vendor();
        $order = $this->makeOrder($vendor);

        $this->actingAs($vendor, 'vendor')
            ->get(route('vendor.orders.show', $order->id))
            ->assertOk()
            ->assertSee('Order #'.$order->id);
    }

    public function test_vendor_cannot_view_another_vendors_order(): void
    {
        $vendor = $this->vendor();
        $rival = Vendor::create([
            'name' => 'Rival', 'shop_name' => 'Rival Shop',
            'email' => 'rival-ow-'.uniqid().'@example.com', 'password' => Hash::make('password'),
            'status' => 'active', 'is_approved' => 1,
        ]);
        $order = $this->makeOrder($rival);

        $this->actingAs($vendor, 'vendor')
            ->get(route('vendor.orders.show', $order->id))
            ->assertNotFound();
    }

    public function test_status_transition_is_recorded_in_history(): void
    {
        $vendor = $this->vendor();
        $order = $this->makeOrder($vendor);

        $this->actingAs($vendor, 'vendor')
            ->post(route('vendor.orders.status', $order->id), ['status' => 'accepted'])
            ->assertRedirect();

        $this->assertSame('accepted', $order->fresh()->status);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'from_status' => 'pending',
            'to_status' => 'accepted',
        ]);
    }

    public function test_invalid_transition_is_rejected(): void
    {
        $vendor = $this->vendor();
        $order = $this->makeOrder($vendor);

        $this->actingAs($vendor, 'vendor')
            ->post(route('vendor.orders.status', $order->id), ['status' => 'delivered'])
            ->assertSessionHas('error');

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame(0, OrderStatusHistory::where('order_id', $order->id)->count());
    }

    public function test_cancelling_an_order_restocks_inventory(): void
    {
        $vendor = $this->vendor();
        $variant = $this->variant($vendor);
        $variant->update(['stock' => 10]);

        $order = $this->makeOrder($vendor);
        OrderDetail::create([
            'order_id' => $order->id,
            'product_id' => $variant->product_id,
            'variant_id' => $variant->id,
            'quantity' => 2,
            'price' => 10000,
        ]);

        $this->actingAs($vendor, 'vendor')
            ->post(route('vendor.orders.status', $order->id), ['status' => 'canceled', 'reason' => 'Out of stock'])
            ->assertRedirect();

        $this->assertSame(12, (int) $variant->fresh()->stock);
        $this->assertDatabaseHas('inventory_movements', [
            'variant_id' => $variant->id,
            'type' => 'return',
            'quantity' => 2,
        ]);
        $this->assertSame('canceled', $order->fresh()->status);
    }

    public function test_order_documents_render(): void
    {
        $vendor = $this->vendor();
        $order = $this->makeOrder($vendor);

        foreach (['invoice', 'receipt', 'packing_slip'] as $type) {
            $this->actingAs($vendor, 'vendor')
                ->get(route('vendor.orders.document', [$order->id, $type]))
                ->assertOk();
        }
    }

    public function test_bulk_action_updates_multiple_orders(): void
    {
        $vendor = $this->vendor();
        $a = $this->makeOrder($vendor);
        $b = $this->makeOrder($vendor);

        $this->actingAs($vendor, 'vendor')
            ->post(route('vendor.orders.bulk'), [
                'action' => 'accept',
                'ids' => [$a->id, $b->id],
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame('accepted', $a->fresh()->status);
        $this->assertSame('accepted', $b->fresh()->status);
    }

    public function test_tracking_details_can_be_updated(): void
    {
        $vendor = $this->vendor();
        $order = $this->makeOrder($vendor);

        $this->actingAs($vendor, 'vendor')
            ->post(route('vendor.orders.tracking', $order->id), [
                'shipping_method' => 'Courier',
                'carrier' => 'Juvo Fleet',
                'tracking_number' => 'TRK-123',
            ])
            ->assertRedirect();

        $order->refresh();
        $this->assertSame('TRK-123', $order->tracking_number);
        $this->assertSame('Juvo Fleet', $order->carrier);
    }
}
