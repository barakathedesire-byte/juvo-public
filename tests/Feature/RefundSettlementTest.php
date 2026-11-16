<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class RefundSettlementTest extends TestCase
{
    use DatabaseTransactions;
    use GrantsAdminAccess;

    protected function customer(): Customer
    {
        return Customer::create([
            'name' => 'Refundee',
            'email' => 'refundee-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
    }

    protected function order(string $paymentStatus = 'paid', float $total = 9000): Order
    {
        return Order::create([
            'vendor_id' => Vendor::query()->value('id'),
            'customer_id' => $this->customer()->id,
            'subtotal' => $total,
            'total_amount' => $total,
            'status' => 'delivered',
            'payment_status' => $paymentStatus,
            'currency' => 'TZS',
        ]);
    }

    protected function paid(Order $order, float $amount): Payment
    {
        return Payment::create([
            'order_id' => $order->id,
            'amount' => $amount,
            'currency' => 'TZS',
            'payment_method' => 'stripe',
            'status' => 'completed',
        ]);
    }

    protected function refund(Order $order, float $amount): Refund
    {
        return Refund::create([
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'vendor_id' => $order->vendor_id,
            'amount' => $amount,
            'currency' => 'TZS',
            'status' => 'requested',
            'reason' => 'Damaged item',
        ]);
    }

    public function test_full_refund_settles_and_marks_the_order_refunded(): void
    {
        $order = $this->order('paid');
        $this->paid($order, 9000);
        $refund = $this->refund($order, 9000);

        $this->actingAs($this->admin())
            ->post(route('admin.refunds.approve', $refund->id))
            ->assertRedirect();

        $refund->refresh();
        $this->assertSame('completed', $refund->status);
        $this->assertSame('9000.00', $refund->amount);
        $this->assertSame('refunded', $order->fresh()->payment_status);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'refunded']);
    }

    public function test_partial_refund_is_capped_and_marks_partial(): void
    {
        $order = $this->order('paid');
        $this->paid($order, 9000);
        $refund = $this->refund($order, 5000);

        $this->actingAs($this->admin())
            ->post(route('admin.refunds.approve', $refund->id))
            ->assertRedirect();

        $refund->refresh();
        $this->assertSame('completed', $refund->status);
        $this->assertSame('5000.00', $refund->amount);
        $this->assertSame('partially_refunded', $order->fresh()->payment_status);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'completed']);
    }

    public function test_refund_amount_is_capped_at_the_paid_amount(): void
    {
        $order = $this->order('paid');
        $this->paid($order, 9000);
        $refund = $this->refund($order, 20000);

        $this->actingAs($this->admin())
            ->post(route('admin.refunds.approve', $refund->id))
            ->assertRedirect();

        $this->assertSame('9000.00', $refund->fresh()->amount);
    }

    public function test_settlement_is_idempotent(): void
    {
        $order = $this->order('paid');
        $this->paid($order, 9000);
        $refund = $this->refund($order, 9000);

        $this->actingAs($this->admin())->post(route('admin.refunds.approve', $refund->id))->assertRedirect();
        $firstReference = $refund->fresh()->refund_id;

        $this->actingAs($this->admin())->post(route('admin.refunds.approve', $refund->id))->assertRedirect();

        $this->assertSame($firstReference, $refund->fresh()->refund_id);
        $this->assertSame('completed', $refund->fresh()->status);
    }

    public function test_uncollected_order_does_not_settle_the_refund(): void
    {
        $order = $this->order('pending');
        $refund = $this->refund($order, 9000);

        $this->actingAs($this->admin())
            ->post(route('admin.refunds.approve', $refund->id))
            ->assertRedirect();

        $refund->refresh();
        $this->assertSame('approved', $refund->status);
        $this->assertSame('pending', $order->fresh()->payment_status);
    }
}
