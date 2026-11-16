<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Vendor;
use App\Notifications\OrderStatusNotification;
use App\Notifications\VendorVerificationNotification;
use App\Services\Vendor\OrderWorkflowService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class OrderNotificationsTest extends TestCase
{
    use DatabaseTransactions;
    use GrantsAdminAccess;

    protected function orderFor(?Customer $customer): Order
    {
        return Order::create([
            'vendor_id' => Vendor::query()->value('id'),
            'customer_id' => $customer?->id,
            'subtotal' => 5000,
            'total_amount' => 5000,
            'status' => 'pending',
            'currency' => 'TZS',
        ]);
    }

    protected function makeCustomer(): Customer
    {
        return Customer::create([
            'name' => 'Notified',
            'email' => 'notified-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
    }

    public function test_status_transition_notifies_the_order_customer(): void
    {
        Notification::fake();

        $customer = $this->makeCustomer();
        $order = $this->orderFor($customer);

        app(OrderWorkflowService::class)->transition($order, 'accepted', null, 'vendor', 1);

        Notification::assertSentTo($customer, OrderStatusNotification::class, function ($notification) use ($order) {
            return $notification->order->id === $order->id
                && $notification->from === 'pending'
                && $notification->to === 'accepted';
        });
    }

    public function test_status_transition_without_a_customer_sends_no_notification(): void
    {
        Notification::fake();

        $order = $this->orderFor(null);

        app(OrderWorkflowService::class)->transition($order, 'accepted', null, 'vendor', 1);

        Notification::assertNothingSent();
    }

    public function test_admin_verification_notifies_the_vendor(): void
    {
        Notification::fake();

        $vendor = Vendor::create([
            'name' => 'Notify Vendor',
            'shop_name' => 'Notify Shop',
            'email' => 'notify-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
            'status' => 'inactive',
            'is_approved' => 0,
            'verification_status' => 'pending',
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.vendors.verification.approve', $vendor->id))
            ->assertRedirect();

        Notification::assertSentTo($vendor, VendorVerificationNotification::class, function ($notification) {
            return $notification->event === 'verified';
        });
    }

    public function test_suspend_notifies_the_vendor_with_the_reason(): void
    {
        Notification::fake();

        $vendor = Vendor::create([
            'name' => 'Suspend Notify',
            'shop_name' => 'Suspend Shop',
            'email' => 'suspend-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
            'is_approved' => 1,
            'verification_status' => 'verified',
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.vendors.suspend', $vendor->id), ['reason' => 'Repeated policy breaches'])
            ->assertRedirect();

        Notification::assertSentTo($vendor, VendorVerificationNotification::class, function ($notification) {
            return $notification->event === 'suspended'
                && $notification->note === 'Repeated policy breaches';
        });
    }
}
