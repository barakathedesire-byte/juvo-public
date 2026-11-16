<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Vendor;
use App\Models\VendorPayout;
use App\Models\VendorTransaction;
use App\Services\Store\PaymentService;
use App\Services\Vendor\OrderWorkflowService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class PaymentPayoutIntegrityTest extends TestCase
{
    use DatabaseTransactions;
    use GrantsAdminAccess;

    protected function order(string $method, string $paymentStatus = 'pending', string $status = 'delivered'): Order
    {
        return Order::create([
            'vendor_id' => Vendor::query()->value('id'),
            'customer_id' => Customer::create([
                'name' => 'Buyer',
                'email' => uniqid('buyer').'@example.com',
                'password' => Hash::make('password'),
                'status' => 'active',
            ])->id,
            'subtotal' => 5000,
            'total_amount' => 5000,
            'status' => $status,
            'payment_status' => $paymentStatus,
            'payment_method' => $method,
            'currency' => 'TZS',
        ]);
    }

    protected function payment(Order $order, string $status = 'pending'): Payment
    {
        return Payment::create([
            'order_id' => $order->id,
            'amount' => 5000,
            'currency' => 'TZS',
            'payment_method' => $order->payment_method,
            'status' => $status,
        ]);
    }

    public function test_duplicate_payment_callbacks_do_not_double_apply(): void
    {
        $order = $this->order('stripe');
        $this->payment($order);
        $service = app(PaymentService::class);

        $service->markCompleted($order, ['id' => 'evt_1'], 'txn_1');
        $service->markCompleted($order, ['id' => 'evt_1'], 'txn_1');

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(1, Payment::where('order_id', $order->id)->where('status', 'completed')->count());
        $this->assertSame(1, Payment::where('order_id', $order->id)->count());
    }

    public function test_failed_payment_is_not_paid_and_a_late_failure_cannot_unpay(): void
    {
        $order = $this->order('stripe');
        $this->payment($order);
        $service = app(PaymentService::class);

        $service->markFailed($order, ['error' => 'declined']);
        $this->assertSame('failed', $order->fresh()->payment_status);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'failed']);

        $service->markCompleted($order, [], 'txn_ok');
        $service->markFailed($order, ['error' => 'late'], 'txn_ok');

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'completed']);
    }

    public function test_cash_on_delivery_is_only_paid_when_the_order_completes(): void
    {
        $order = $this->order('payondelivery');
        $this->payment($order, 'pending');

        $this->assertSame('pending', $order->fresh()->payment_status);

        app(OrderWorkflowService::class)->transition($order, 'completed', 'Delivered and paid', 'vendor', $order->vendor_id);

        $order->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'completed']);
    }

    public function test_duplicate_payout_release_does_not_settle_twice(): void
    {
        $vendor = Vendor::query()->firstOrFail();

        $payout = VendorPayout::create([
            'vendor_id' => $vendor->id,
            'reference' => 'PAY-'.strtoupper(uniqid()),
            'amount' => 1000,
            'currency' => 'TZS',
            'method' => 'manual',
            'status' => 'requested',
            'requested_at' => now(),
        ]);

        $this->actingAs($this->admin())->post(route('admin.payouts.pay', $payout))->assertRedirect();
        $this->assertSame('paid', $payout->fresh()->status);
        $this->assertSame(1, VendorTransaction::where('reference', 'payout:'.$payout->id)->count());

        $this->actingAs($this->admin())->post(route('admin.payouts.pay', $payout))->assertRedirect();

        $this->assertSame('paid', $payout->fresh()->status);
        $this->assertSame(1, VendorTransaction::where('reference', 'payout:'.$payout->id)->count());
    }
}
