<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Refund;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class ReturnRefundWorkflowTest extends TestCase
{
    use DatabaseTransactions;
    use GrantsAdminAccess;

    protected function makeCustomer(): Customer
    {
        return Customer::create([
            'name' => 'Returner',
            'email' => 'returner-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
    }

    protected function makeOrder(Customer $customer, string $status = 'delivered'): Order
    {
        return Order::create([
            'vendor_id' => Vendor::query()->value('id'),
            'customer_id' => $customer->id,
            'subtotal' => 9000,
            'total_amount' => 9000,
            'status' => $status,
            'currency' => 'TZS',
        ]);
    }

    public function test_customer_can_request_a_return_for_a_delivered_order(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, 'delivered');

        $this->actingAs($customer, 'customer')
            ->post(route('customer.orders.return', $order->id), ['reason' => 'Item arrived damaged and unusable'])
            ->assertRedirect();

        $this->assertDatabaseHas('refunds', [
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'vendor_id' => $order->vendor_id,
            'status' => 'requested',
            'amount' => 9000,
        ]);
    }

    public function test_return_is_rejected_for_a_non_returnable_order(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, 'pending');

        $this->actingAs($customer, 'customer')
            ->post(route('customer.orders.return', $order->id), ['reason' => 'Changed my mind about this one'])
            ->assertStatus(422);

        $this->assertDatabaseCount('refunds', 0);
    }

    public function test_duplicate_open_return_request_is_blocked(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, 'completed');

        Refund::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'vendor_id' => $order->vendor_id,
            'amount' => 9000,
            'currency' => 'TZS',
            'status' => 'requested',
            'reason' => 'First request',
        ]);

        $this->actingAs($customer, 'customer')
            ->post(route('customer.orders.return', $order->id), ['reason' => 'Second attempt at returning'])
            ->assertStatus(422);
    }

    public function test_admin_approval_marks_the_order_returned(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, 'delivered');

        $refund = Refund::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'vendor_id' => $order->vendor_id,
            'amount' => 9000,
            'currency' => 'TZS',
            'status' => 'requested',
            'reason' => 'Damaged item',
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.refunds.approve', $refund->id))
            ->assertRedirect();

        $this->assertSame('approved', $refund->fresh()->status);
        $this->assertSame('returned', $order->fresh()->status);
    }

    public function test_admin_rejection_requires_a_reason(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, 'delivered');

        $refund = Refund::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'vendor_id' => $order->vendor_id,
            'amount' => 9000,
            'currency' => 'TZS',
            'status' => 'requested',
            'reason' => 'Damaged item',
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.refunds.reject', $refund->id), [])
            ->assertSessionHasErrors('admin_note');

        $this->actingAs($this->admin())
            ->post(route('admin.refunds.reject', $refund->id), ['admin_note' => 'Outside the return window'])
            ->assertRedirect();

        $refund->refresh();
        $this->assertSame('rejected', $refund->status);
        $this->assertSame('Outside the return window', $refund->admin_note);
    }

    public function test_vendors_have_no_return_management_surface(): void
    {
        $vendor = Vendor::query()->firstOrFail();

        $this->actingAs($vendor, 'vendor')
            ->get('/vendor/returns')
            ->assertNotFound();
    }
}
