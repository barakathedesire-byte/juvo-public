<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Rider\Rider;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\Vendor;
use App\Services\Admin\AuditLogger;
use App\Services\Support\SupportSlaService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SupportController extends Controller
{
    protected const STATUSES = ['open', 'pending', 'resolved', 'closed'];
    protected const PRIORITIES = ['low', 'normal', 'high', 'urgent'];
    protected const CATEGORIES = ['delivery', 'cod', 'payment', 'rider', 'vendor', 'location', 'other'];
    protected const DISPUTE_TYPES = ['failed_delivery', 'delayed_delivery', 'missing_package', 'damaged_package', 'wrong_confirmation', 'rider_misconduct', 'cod_discrepancy', 'refund', 'payment', 'delivery_fee', 'wrong_location', 'other'];

    public function __construct(
        protected SupportSlaService $sla,
        protected AuditLogger $audit,
    ) {}

    public function index(Request $request)
    {
        $this->canSupport();

        $query = SupportTicket::with(['customer', 'vendor', 'rider', 'order', 'logisticsAssignee'])
            ->latest();

        $this->applyFilters($query, $request);

        $tickets = $query->paginate(25)->withQueryString();

        $kpis = [
            'open' => SupportTicket::where('status', 'open')->count(),
            'unassigned' => SupportTicket::whereNull('assigned_logistics_admin_id')->whereNotIn('status', ['resolved', 'closed'])->count(),
            'in_progress' => SupportTicket::where('status', 'pending')->count(),
            'high_priority' => SupportTicket::whereIn('priority', ['high', 'urgent'])->whereNotIn('status', ['resolved', 'closed'])->count(),
            'escalated' => SupportTicket::where('escalation_level', '>', 0)->whereNotIn('status', ['resolved', 'closed'])->count(),
            'resolved' => SupportTicket::where('status', 'resolved')->count(),
            'closed' => SupportTicket::where('status', 'closed')->count(),
            'overdue' => SupportTicket::whereNotIn('status', ['resolved', 'closed'])
                ->where(function ($q) {
                    $q->where('first_response_due_at', '<', now())->whereNull('first_responded_at')
                      ->orWhere('resolution_due_at', '<', now());
                })->count(),
        ];

        $admins = \App\Models\Logistics\LogisticsAdmin::where('is_active', true)->orderBy('name')->get();

        return view('logistics.support.index', compact('tickets', 'kpis', 'admins', 'request'));
    }

    public function show(SupportTicket $ticket)
    {
        $this->canSupport();
        $ticket->load(['customer', 'vendor', 'rider', 'order.shippingAddress', 'logisticsAssignee', 'messages' => fn ($q) => $q->orderBy('created_at')]);
        $admins = \App\Models\Logistics\LogisticsAdmin::where('is_active', true)->orderBy('name')->get();
        $slaState = $this->sla->state($ticket);

        return view('logistics.support.show', compact('ticket', 'admins', 'slaState'));
    }

    public function store(Request $request)
    {
        $this->canSupport();
        $data = $request->validate([
            'subject' => 'required|string|max:200',
            'category' => 'nullable|in:'.implode(',', self::CATEGORIES),
            'dispute_type' => 'nullable|in:'.implode(',', self::DISPUTE_TYPES),
            'priority' => 'required|in:'.implode(',', self::PRIORITIES),
            'customer_id' => 'nullable|exists:customers,id',
            'vendor_id' => 'nullable|exists:vendors,id',
            'rider_id' => 'nullable|exists:riders,id',
            'order_id' => 'nullable|exists:orders,id',
            'product_id' => 'nullable|exists:products,id',
            'message' => 'required|string|min:3|max:5000',
            'is_internal' => 'nullable|boolean',
        ]);

        $ticket = SupportTicket::create([
            'ticket_number' => $this->uniqueTicketNumber(),
            'customer_id' => $data['customer_id'] ?? null,
            'vendor_id' => $data['vendor_id'] ?? null,
            'rider_id' => $data['rider_id'] ?? null,
            'order_id' => $data['order_id'] ?? null,
            'product_id' => $data['product_id'] ?? null,
            'subject' => $data['subject'],
            'category' => $data['category'] ?? 'other',
            'dispute_type' => $data['dispute_type'] ?? null,
            'priority' => $data['priority'],
            'status' => 'open',
            'last_reply_at' => now(),
        ]);

        SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'sender_type' => 'staff',
            'sender_id' => auth('logistics_admin')->id(),
            'body' => $data['message'],
            'is_internal' => (bool) ($data['is_internal'] ?? false),
        ]);

        $this->audit->log('logistics.support.ticket_created', $ticket, [], $ticket->toArray(), null, auth('logistics_admin')->user());

        app(\App\Services\Logistics\LogisticsEventDispatcher::class)->dispatch('support.ticket.created', ['ticket_id' => $ticket->id]);

        return redirect()->route('logistics.support.show', $ticket)->with('success', 'Support ticket created: '.$ticket->ticket_number);
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        $this->canSupport();
        $data = $request->validate([
            'body' => 'required|string|min:1|max:5000',
            'is_internal' => 'nullable|boolean',
            'attachments.*' => 'nullable|file|max:5120',
        ]);

        $paths = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $paths[] = $file->store('support-attachments', 'public');
            }
        }

        SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'sender_type' => 'staff',
            'sender_id' => auth('logistics_admin')->id(),
            'body' => $data['body'],
            'attachments' => $paths ?: null,
            'is_internal' => (bool) ($data['is_internal'] ?? false),
        ]);

        $ticket->last_reply_at = now();
        if ($ticket->status === 'open' && empty($data['is_internal'])) {
            $ticket->status = 'pending';
        }
        $ticket->save();

        if (empty($data['is_internal'])) {
            $this->sla->markFirstResponse($ticket);
        }

        return back()->with('success', 'Reply added.');
    }

    public function update(Request $request, SupportTicket $ticket)
    {
        $this->canSupport();
        $data = $request->validate([
            'status' => 'required|in:'.implode(',', self::STATUSES),
            'priority' => 'required|in:'.implode(',', self::PRIORITIES),
            'resolution_category' => 'nullable|string|max:64',
            'resolution_notes' => 'nullable|string|max:5000',
        ]);

        $before = ['status' => $ticket->status, 'priority' => $ticket->priority];
        $ticket->status = $data['status'];
        $ticket->priority = $data['priority'];
        $ticket->resolution_category = $data['resolution_category'] ?? $ticket->resolution_category;
        $ticket->resolution_notes = $data['resolution_notes'] ?? $ticket->resolution_notes;

        if (in_array($data['status'], ['resolved', 'closed'], true)) {
            $ticket->resolved_at = $ticket->resolved_at ?: now();
        } else {
            $ticket->resolved_at = null;
        }

        $ticket->save();

        $this->audit->log('logistics.support.ticket_updated', $ticket, $before, [
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'resolution_category' => $ticket->resolution_category,
        ], null, auth('logistics_admin')->user());

        return back()->with('success', 'Ticket updated.');
    }

    public function assign(Request $request, SupportTicket $ticket)
    {
        $this->canSupport();
        $data = $request->validate([
            'assigned_logistics_admin_id' => 'nullable|exists:logistics_admins,id',
        ]);

        $before = ['assigned_logistics_admin_id' => $ticket->assigned_logistics_admin_id];
        $ticket->assigned_logistics_admin_id = $data['assigned_logistics_admin_id'] ?? null;
        $ticket->save();

        $this->audit->log('logistics.support.ticket_assigned', $ticket, $before, ['assigned_logistics_admin_id' => $ticket->assigned_logistics_admin_id], null, auth('logistics_admin')->user());

        return back()->with('success', 'Assignment updated.');
    }

    public function escalate(Request $request, SupportTicket $ticket)
    {
        $this->canSupport();
        $data = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $before = ['priority' => $ticket->priority, 'escalation_level' => $ticket->escalation_level];
        $ticket->escalation_level = min(($ticket->escalation_level ?? 0) + 1, 3);
        $ticket->escalated_at = now();
        $ticket->priority = match ($ticket->priority) {
            'low' => 'normal',
            'normal' => 'high',
            default => 'urgent',
        };
        $ticket->save();

        if (! empty($data['reason'])) {
            SupportTicketMessage::create([
                'support_ticket_id' => $ticket->id,
                'sender_type' => 'staff',
                'sender_id' => auth('logistics_admin')->id(),
                'body' => 'Escalated: '.$data['reason'],
                'is_internal' => true,
            ]);
        }

        $this->audit->log('logistics.support.ticket_escalated', $ticket, $before, [
            'priority' => $ticket->priority,
            'escalation_level' => $ticket->escalation_level,
        ], $data['reason'] ?? null, auth('logistics_admin')->user());

        return back()->with('success', 'Ticket escalated.');
    }

    private function applyFilters($query, Request $request): void
    {
        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }
        if ($priority = $request->string('priority')->toString()) {
            $query->where('priority', $priority);
        }
        if ($category = $request->string('category')->toString()) {
            $query->where('category', $category);
        }
        if ($disputeType = $request->string('dispute_type')->toString()) {
            $query->where('dispute_type', $disputeType);
        }
        if ($request->filled('assigned_logistics_admin_id')) {
            $value = $request->string('assigned_logistics_admin_id')->toString();
            $value === 'unassigned'
                ? $query->whereNull('assigned_logistics_admin_id')
                : $query->where('assigned_logistics_admin_id', (int) $value);
        }
        if ($request->filled('order_id')) {
            $query->where('order_id', $request->integer('order_id'));
        }
        if ($request->filled('rider_id')) {
            $query->where('rider_id', $request->integer('rider_id'));
        }
        if ($search = $request->string('q')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%");
            });
        }
    }

    private function uniqueTicketNumber(): string
    {
        do {
            $number = 'TKT-'.now()->format('Ymd').'-'.strtoupper(Str::random(5));
        } while (SupportTicket::where('ticket_number', $number)->exists());

        return $number;
    }

    private function canSupport(): void
    {
        $admin = auth('logistics_admin')->user();
        abort_unless($admin && ($admin->isSuperAdmin() || $admin->canAccessLogisticsModule('support')), 403);
    }
}
