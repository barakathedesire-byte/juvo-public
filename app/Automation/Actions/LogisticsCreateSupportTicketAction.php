<?php

namespace App\Automation\Actions;

use App\Automation\AutomationAction;
use App\Models\AutomationRun;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Support\Str;

class LogisticsCreateSupportTicketAction implements AutomationAction
{
    public function key(): string { return 'logistics.create_support_ticket'; }
    public function label(): string { return 'Create logistics support ticket'; }
    public function mutates(): bool { return true; }

    public function handle(array $input, AutomationRun $run): array
    {
        do {
            $number = 'TKT-'.now()->format('Ymd').'-'.strtoupper(Str::random(5));
        } while (SupportTicket::where('ticket_number', $number)->exists());

        $ticket = SupportTicket::create([
            'ticket_number' => $number,
            'customer_id' => $input['customer_id'] ?? null,
            'vendor_id' => $input['vendor_id'] ?? null,
            'rider_id' => $input['rider_id'] ?? null,
            'order_id' => $input['order_id'] ?? null,
            'subject' => $input['subject'] ?? 'Automated support ticket',
            'category' => $input['category'] ?? 'other',
            'dispute_type' => $input['dispute_type'] ?? null,
            'priority' => $input['priority'] ?? 'normal',
            'status' => 'open',
        ]);

        if (! empty($input['message'])) {
            SupportTicketMessage::create([
                'support_ticket_id' => $ticket->id,
                'sender_type' => 'system',
                'sender_id' => null,
                'body' => $input['message'],
                'is_internal' => true,
            ]);
        }

        return ['ticket_id' => $ticket->id, 'ticket_number' => $ticket->ticket_number];
    }
}
