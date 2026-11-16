<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    protected const SESSION_KEY = 'juvo_chat_token';

    protected const MAX_BODY = 5000;

    public function start(Request $request)
    {
        $data = $request->validate([
            'name' => [$this->customer() ? 'nullable' : 'required', 'string', 'max:150'],
            'email' => [$this->customer() ? 'nullable' : 'required', 'email', 'max:150'],
            'subject' => ['nullable', 'string', 'max:200'],
            'message' => ['required', 'string', 'min:2', 'max:'.self::MAX_BODY],
        ]);

        $customer = $this->customer();

        $ticket = SupportTicket::create([
            'ticket_number' => $this->uniqueTicketNumber(),
            'customer_id' => $customer?->id,
            'guest_name' => $customer ? null : $data['name'],
            'guest_email' => $customer ? null : $data['email'],
            'subject' => $data['subject'] ?? null ?: 'Live chat',
            'category' => 'general',
            'priority' => 'normal',
            'status' => 'open',
            'last_reply_at' => now(),
        ]);

        SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'sender_type' => $customer ? 'customer' : 'guest',
            'sender_id' => $customer?->id,
            'body' => $data['message'],
            'is_internal' => false,
        ]);

        Session::put(self::SESSION_KEY, $this->tokenFor($ticket));

        return $this->threadPayload($ticket);
    }

    public function thread(Request $request)
    {
        $ticket = $this->resolveTicket($request);

        if (! $ticket) {
            return response()->json(['found' => false]);
        }

        return $this->threadPayload($ticket);
    }

    public function send(Request $request)
    {
        $ticket = $this->resolveTicket($request);

        if (! $ticket) {
            return response()->json(['error' => 'No active conversation. Start a new chat.'], 404);
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:'.self::MAX_BODY],
        ]);

        $customer = $this->customer();

        SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'sender_type' => $customer ? 'customer' : 'guest',
            'sender_id' => $customer?->id,
            'body' => $data['body'],
            'is_internal' => false,
        ]);

        if ($ticket->status === 'resolved' || $ticket->status === 'closed') {
            $ticket->status = 'open';
            $ticket->reopened_count = ($ticket->reopened_count ?? 0) + 1;
        }

        $ticket->last_reply_at = now();
        $ticket->save();

        return $this->threadPayload($ticket);
    }

    public function poll(Request $request)
    {
        $ticket = $this->resolveTicket($request);

        if (! $ticket) {
            return response()->json(['found' => false]);
        }

        $data = $request->validate([
            'after' => ['nullable', 'integer', 'min:0'],
        ]);

        $query = $ticket->messages()
            ->where('is_internal', false)
            ->where('id', '>', (int) ($data['after'] ?? 0))
            ->orderBy('id');

        return response()->json([
            'found' => true,
            'ticket_number' => $ticket->ticket_number,
            'status' => $ticket->status,
            'messages' => $query->get()->map(fn (SupportTicketMessage $m) => $this->presentMessage($m))->all(),
        ]);
    }

    public function end(Request $request)
    {
        $ticket = $this->resolveTicket($request);

        Session::forget(self::SESSION_KEY);

        if ($ticket && in_array($ticket->status, ['open', 'pending'], true)) {
            $ticket->status = 'closed';
            $ticket->save();
        }

        return response()->json(['ended' => true]);
    }

    protected function customer()
    {
        return Auth::guard('customer')->user();
    }

    protected function tokenFor(SupportTicket $ticket): string
    {
        return $ticket->id.'.'.substr(hash_hmac('sha256', (string) $ticket->id, $this->tokenSalt()), 0, 32);
    }

    protected function tokenSalt(): string
    {
        return (string) config('app.key');
    }

    protected function resolveTicket(Request $request): ?SupportTicket
    {
        $token = $request->input('token') ?: Session::get(self::SESSION_KEY);

        if (! $token || ! str_contains($token, '.')) {
            return null;
        }

        [$id, $signature] = explode('.', $token, 2);

        $expected = substr(hash_hmac('sha256', (string) $id, $this->tokenSalt()), 0, 32);

        if (! hash_equals($expected, (string) $signature)) {
            return null;
        }

        $ticket = SupportTicket::find((int) $id);

        if (! $ticket) {
            return null;
        }

        $customer = $this->customer();

        if ($ticket->customer_id && (! $customer || $customer->id !== $ticket->customer_id)) {
            return null;
        }

        return $ticket;
    }

    protected function threadPayload(SupportTicket $ticket): \Illuminate\Http\JsonResponse
    {
        $messages = $ticket->messages()
            ->where('is_internal', false)
            ->orderBy('id')
            ->get()
            ->map(fn (SupportTicketMessage $m) => $this->presentMessage($m))
            ->all();

        return response()->json([
            'found' => true,
            'token' => $this->tokenFor($ticket),
            'ticket_number' => $ticket->ticket_number,
            'status' => $ticket->status,
            'messages' => $messages,
        ]);
    }

    protected function presentMessage(SupportTicketMessage $message): array
    {
        $fromStaff = in_array($message->sender_type, ['staff', 'agent', 'admin'], true);

        return [
            'id' => $message->id,
            'from' => $fromStaff ? 'agent' : 'me',
            'body' => $message->body,
            'at' => optional($message->created_at)->toIso8601String(),
        ];
    }

    protected function uniqueTicketNumber(): string
    {
        do {
            $number = 'CHAT-'.now()->format('Ymd').'-'.strtoupper(Str::random(5));
        } while (SupportTicket::where('ticket_number', $number)->exists());

        return $number;
    }
}
