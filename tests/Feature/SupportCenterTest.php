<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class SupportCenterTest extends TestCase
{
    use DatabaseTransactions;
    use GrantsAdminAccess;

    protected function makeCustomer(): Customer
    {
        return Customer::create([
            'name' => 'Supporter',
            'email' => 'support-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
    }

    public function test_support_hub_page_renders(): void
    {
        $this->get(route('support.index'))->assertOk()->assertSee('Help &amp; Support', false);
    }

    public function test_guest_contact_creates_a_ticket_and_message(): void
    {
        $this->post(route('support.contact'), [
            'name' => 'Guest User',
            'email' => 'guest-'.uniqid().'@example.com',
            'subject' => 'Delivery question',
            'category' => 'order',
            'message' => 'My order has not arrived after a week.',
        ])->assertRedirect();

        $ticket = SupportTicket::latest('id')->first();
        $this->assertNotNull($ticket);
        $this->assertTrue(str_starts_with($ticket->ticket_number, 'TKT-'));
        $this->assertSame('guest', $ticket->messages->first()->sender_type);
        $this->assertNull($ticket->customer_id);
        $this->assertNotNull($ticket->guest_email);
    }

    public function test_authenticated_customer_contact_links_to_account(): void
    {
        $customer = $this->makeCustomer();

        $this->actingAs($customer, 'customer')->post(route('support.contact'), [
            'subject' => 'Refund status',
            'category' => 'payment',
            'message' => 'Please check the status of my refund.',
        ])->assertRedirect();

        $ticket = SupportTicket::latest('id')->first();
        $this->assertSame($customer->id, $ticket->customer_id);
        $this->assertNull($ticket->guest_email);
        $this->assertSame('customer', $ticket->messages->first()->sender_type);
    }

    public function test_order_tracking_finds_the_order_with_matching_email(): void
    {
        $customer = $this->makeCustomer();
        $order = Order::create([
            'vendor_id' => Vendor::query()->value('id'),
            'customer_id' => $customer->id,
            'subtotal' => 4000,
            'total_amount' => 4000,
            'status' => 'dispatched',
            'currency' => 'TZS',
        ]);

        $this->post(route('support.track'), [
            'order_id' => $order->id,
            'email' => $customer->email,
        ])->assertOk()->assertSee('Order #'.$order->id);
    }

    public function test_order_tracking_rejects_a_wrong_email(): void
    {
        $customer = $this->makeCustomer();
        $order = Order::create([
            'vendor_id' => Vendor::query()->value('id'),
            'customer_id' => $customer->id,
            'subtotal' => 4000,
            'total_amount' => 4000,
            'status' => 'dispatched',
            'currency' => 'TZS',
        ]);

        $this->post(route('support.track'), [
            'order_id' => $order->id,
            'email' => 'someone-else@example.com',
        ])->assertSessionHasErrors('order_id');
    }

    public function test_admin_can_view_and_reply_to_a_ticket(): void
    {
        $ticket = SupportTicket::create([
            'ticket_number' => 'TKT-TEST-0001',
            'guest_name' => 'Guest',
            'guest_email' => 'guest@example.com',
            'subject' => 'Need help',
            'category' => 'general',
            'priority' => 'normal',
            'status' => 'open',
            'last_reply_at' => now(),
        ]);

        SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'sender_type' => 'guest',
            'body' => 'Please help me.',
        ]);

        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.support-tickets.show', $ticket->id))
            ->assertOk()
            ->assertSee('Please help me.');

        $this->actingAs($admin)
            ->post(route('admin.support-tickets.reply', $ticket->id), ['body' => 'We are looking into it.'])
            ->assertRedirect();

        $this->assertDatabaseHas('support_ticket_messages', [
            'support_ticket_id' => $ticket->id,
            'sender_type' => 'staff',
            'body' => 'We are looking into it.',
        ]);
    }
}
