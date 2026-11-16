<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HelpCenterChatTest extends TestCase
{
    use DatabaseTransactions;

    protected function makeCustomer(): Customer
    {
        return Customer::create([
            'name' => 'Chatty',
            'email' => 'chat-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
    }

    public function test_help_hub_renders_topics_and_faq(): void
    {
        $this->get(route('help.index'))
            ->assertOk()
            ->assertSee('How can we help?')
            ->assertSee('Orders &amp; tracking', false)
            ->assertSee('Frequently asked questions');
    }

    public function test_offline_page_renders(): void
    {
        $this->get(route('offline'))->assertOk()->assertSee('You are offline');
    }

    public function test_manifest_and_service_worker_are_served(): void
    {
        $manifest = public_path('manifest.webmanifest');
        $worker = public_path('sw.js');

        $this->assertFileExists($manifest);
        $this->assertFileExists($worker);
        $this->assertStringContainsString('"short_name": "Juvo"', file_get_contents($manifest));
        $this->assertStringContainsString('juvo-v1', file_get_contents($worker));
    }

    public function test_pwa_meta_is_present_on_storefront(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('rel="manifest"', false)
            ->assertSee('apple-mobile-web-app-capable', false)
            ->assertSee('msapplication-TileColor', false);
    }

    public function test_guest_can_start_a_chat_and_creates_a_ticket(): void
    {
        $response = $this->postJson(route('chat.start'), [
            'name' => 'Guest Chat',
            'email' => 'guest-chat-'.uniqid().'@example.com',
            'subject' => 'Where is my order?',
            'message' => 'Hello, can you help me track it?',
        ])->assertOk()->assertJson(['found' => true]);

        $ticket = SupportTicket::latest('id')->first();
        $this->assertNotNull($ticket);
        $this->assertTrue(str_starts_with($ticket->ticket_number, 'CHAT-'));
        $this->assertNull($ticket->customer_id);
        $this->assertSame('guest', $ticket->messages->first()->sender_type);

        $response->assertJsonPath('ticket_number', $ticket->ticket_number);
    }

    public function test_customer_chat_links_to_account(): void
    {
        $customer = $this->makeCustomer();

        $this->actingAs($customer, 'customer')->postJson(route('chat.start'), [
            'message' => 'I need help with my account.',
        ])->assertOk()->assertJson(['found' => true]);

        $ticket = SupportTicket::latest('id')->first();
        $this->assertSame($customer->id, $ticket->customer_id);
        $this->assertNull($ticket->guest_email);
        $this->assertSame('customer', $ticket->messages->first()->sender_type);
    }

    public function test_customer_can_send_and_poll_messages(): void
    {
        $payload = $this->postJson(route('chat.start'), [
            'name' => 'Guest Poll',
            'email' => 'guest-poll-'.uniqid().'@example.com',
            'message' => 'First message.',
        ])->assertOk()->json();

        $token = $payload['token'];

        $this->postJson(route('chat.message'), [
            'token' => $token,
            'body' => 'Second message.',
        ])->assertOk()->assertJsonCount(2, 'messages');

        $reply = SupportTicketMessage::create([
            'support_ticket_id' => SupportTicket::latest('id')->first()->id,
            'sender_type' => 'staff',
            'body' => 'An agent reply.',
            'is_internal' => false,
        ]);

        SupportTicketMessage::create([
            'support_ticket_id' => SupportTicket::latest('id')->first()->id,
            'sender_type' => 'staff',
            'body' => 'Secret internal note.',
            'is_internal' => true,
        ]);

        $this->postJson(route('chat.poll'), [
            'token' => $token,
            'after' => $reply->id - 1,
        ])
            ->assertOk()
            ->assertJsonPath('messages.0.from', 'agent')
            ->assertJsonPath('messages.0.body', 'An agent reply.')
            ->assertJsonMissing(['body' => 'Secret internal note.']);
    }

    public function test_chat_rejects_a_forged_token(): void
    {
        $this->postJson(route('chat.start'), [
            'name' => 'Guest Forge',
            'email' => 'guest-forge-'.uniqid().'@example.com',
            'message' => 'Opening a chat.',
        ])->assertOk();

        $ticket = SupportTicket::latest('id')->first();

        $this->postJson(route('chat.message'), [
            'token' => $ticket->id.'.deadbeefdeadbeefdeadbeefdeadbeef',
            'body' => 'Hijack attempt.',
        ])->assertStatus(404);
    }

    public function test_chat_cannot_be_read_by_another_customer(): void
    {
        $owner = $this->makeCustomer();

        $payload = $this->actingAs($owner, 'customer')->postJson(route('chat.start'), [
            'message' => 'Private question.',
        ])->assertOk()->json();

        $intruder = $this->makeCustomer();

        $this->actingAs($intruder, 'customer')->postJson(route('chat.poll'), [
            'token' => $payload['token'],
            'after' => 0,
        ])->assertOk()->assertJson(['found' => false]);
    }

    public function test_chat_send_without_a_conversation_is_rejected(): void
    {
        $this->postJson(route('chat.message'), ['body' => 'orphan'])
            ->assertStatus(404);
    }
}
