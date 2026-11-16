<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportCannedResponse;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Services\Access\AuthorizationService;
use App\Services\Admin\AuditLogger;
use App\Services\Support\SupportSlaService;
use App\Support\Access\AccessScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\Facades\DataTables;

class SupportTicketController extends Controller
{
    protected const STATUSES = ['open', 'pending', 'resolved', 'closed'];

    protected const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    public function __construct(
        protected SupportSlaService $sla,
        protected AuditLogger $audit,
        protected AuthorizationService $auth,
    ) {}

    public function index(Request $request)
    {
        return view('admin.support.index', [
            'agents' => $this->agents(),
            'statuses' => self::STATUSES,
            'priorities' => self::PRIORITIES,
            'filters' => $request->only(['status', 'priority', 'assigned_to', 'sla']),
        ]);
    }

    public function getData(Request $request)
    {
        if (! $request->ajax()) {
            return;
        }

        $tickets = SupportTicket::with(['customer', 'assignee'])
            ->withCount('messages')
            ->select('support_tickets.*');

        $this->restrictToScope($tickets, $request->user());

        if ($status = $request->input('status')) {
            $tickets->where('status', $status);
        }

        if ($priority = $request->input('priority')) {
            $tickets->where('priority', $priority);
        }

        if ($assignedTo = $request->input('assigned_to')) {
            $assignedTo === 'unassigned'
                ? $tickets->whereNull('assigned_to')
                : $tickets->where('assigned_to', $assignedTo);
        }

        return DataTables::of($tickets)
            ->addColumn('requester', fn ($row) => $row->customer->name ?? ($row->guest_name ?: 'Guest'))
            ->addColumn('contact', fn ($row) => $row->customer->email ?? ($row->guest_email ?: '—'))
            ->addColumn('assignee', fn ($row) => $row->assignee->name ?? '—')
            ->addColumn('sla', fn ($row) => $this->slaBadge($row))
            ->addColumn('action', function ($row) {
                return '<a href="'.route('admin.support-tickets.show', $row->id).'"
                    class="border border-primary dt-show rounded-3 d-inline-block px-2 py-1">
                        <i class="bi bi-eye-fill text-primary"></i>
                    </a>';
            })
            ->rawColumns(['sla', 'action'])
            ->make(true);
    }

    public function show($id)
    {
        $ticket = SupportTicket::with(['customer', 'assignee', 'messages' => fn ($q) => $q->orderBy('created_at')])
            ->findOrFail($id);

        $this->authorizeTicket($ticket);

        return view('admin.support.show', [
            'ticket' => $ticket,
            'statuses' => self::STATUSES,
            'priorities' => self::PRIORITIES,
            'agents' => $this->agents(),
            'canned' => SupportCannedResponse::active()->orderBy('title')->get(),
            'slaState' => $this->sla->state($ticket),
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        $this->authorizeTicket($ticket, ['support.reply', 'manage-support']);

        $data = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:5000'],
            'is_internal' => ['nullable', 'boolean'],
        ]);

        $isInternal = (bool) ($data['is_internal'] ?? false);

        SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'sender_type' => 'staff',
            'sender_id' => Auth::id(),
            'body' => $data['body'],
            'is_internal' => $isInternal,
        ]);

        $ticket->last_reply_at = now();
        if ($ticket->status === 'open' && ! $isInternal) {
            $ticket->status = 'pending';
        }
        $ticket->save();

        if (! $isInternal) {
            $this->sla->markFirstResponse($ticket);
        }

        return back()->with('success', 'Reply added.');
    }

    public function status(Request $request, SupportTicket $ticket)
    {
        $this->authorizeTicket($ticket, ['support.reply', 'support.close', 'support.reopen', 'manage-support']);

        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', self::STATUSES)],
            'priority' => ['required', 'in:'.implode(',', self::PRIORITIES)],
        ]);

        $before = ['status' => $ticket->status, 'priority' => $ticket->priority];
        $newStatus = $data['status'];
        $canManage = Auth::user()->can('manage-support');

        if (in_array($newStatus, ['resolved', 'closed'], true)) {
            abort_unless($canManage || Auth::user()->can('support.close'), 403);
        }

        if (in_array($ticket->status, ['resolved', 'closed'], true) && in_array($newStatus, ['open', 'pending'], true)) {
            abort_unless($canManage || Auth::user()->can('support.reopen'), 403);
            $ticket->reopened_count++;
        }

        $ticket->resolved_at = match ($newStatus) {
            'resolved' => $ticket->resolved_at ?: now(),
            'closed' => $ticket->resolved_at,
            default => null,
        };

        $ticket->status = $newStatus;
        $ticket->priority = $data['priority'];
        $ticket->save();

        $this->audit->log('support.ticket.status', $ticket, $before, [
            'status' => $ticket->status,
            'priority' => $ticket->priority,
        ]);

        return back()->with('success', 'Ticket updated.');
    }

    public function assign(Request $request, SupportTicket $ticket)
    {
        $this->authorizeTicket($ticket, ['support.assign', 'manage-support']);

        $data = $request->validate([
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $before = ['assigned_to' => $ticket->assigned_to];
        $ticket->assigned_to = $data['assigned_to'] ?? null;
        $ticket->save();

        $this->audit->log('support.ticket.assigned', $ticket, $before, ['assigned_to' => $ticket->assigned_to]);

        return back()->with('success', 'Assignment updated.');
    }

    public function escalate(Request $request, SupportTicket $ticket)
    {
        $this->authorizeTicket($ticket, ['support.escalate', 'manage-support']);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $before = ['priority' => $ticket->priority, 'escalation_level' => $ticket->escalation_level];

        $ticket->escalation_level = min(($ticket->escalation_level ?? 0) + 1, 3);
        $ticket->escalated_at = now();
        $ticket->priority = $this->escalatedPriority($ticket->priority);
        $ticket->save();

        if ($data['reason'] ?? null) {
            SupportTicketMessage::create([
                'support_ticket_id' => $ticket->id,
                'sender_type' => 'staff',
                'sender_id' => Auth::id(),
                'body' => 'Escalated: '.$data['reason'],
                'is_internal' => true,
            ]);
        }

        $this->audit->log('support.ticket.escalated', $ticket, $before, [
            'priority' => $ticket->priority,
            'escalation_level' => $ticket->escalation_level,
        ], $data['reason'] ?? null);

        return back()->with('success', 'Ticket escalated.');
    }

    /**
     * Narrow the ticket query to the staff member's record scope. Agents
     * limited to their own queue only see tickets assigned to them; vendor
     * scoped staff only see their vendors' tickets.
     */
    protected function restrictToScope($query, ?User $user)
    {
        if (! $user) {
            return $query;
        }

        $scope = $this->auth->scope($user);

        if (in_array($scope->type, [AccessScope::TICKETS, AccessScope::OWN], true)) {
            $query->where('assigned_to', $user->id);
        } elseif ($scope->type === AccessScope::VENDORS) {
            $query->whereIn('vendor_id', $scope->ids ?: [-1]);
        }

        return $query;
    }

    /**
     * Authorize a ticket action, enforcing scope on retrieval so restricted
     * records cannot be opened or mutated by direct id. When $permission is
     * null only the record-level scope is enforced (the route middleware
     * already gates the baseline action).
     *
     * @param  string|array<int, string>|null  $permission
     */
    protected function authorizeTicket(?SupportTicket $ticket, string|array|null $permission = null): void
    {
        $user = Auth::user();

        $context = [
            'assigned_to' => $ticket?->assigned_to,
            'vendor_id' => $ticket?->vendor_id,
        ];

        if ($permission === null) {
            if (! $this->auth->withinScope($user, $context)) {
                abort(403);
            }

            return;
        }

        $this->auth->authorize($user, $permission, $context);
    }

    protected function escalatedPriority(string $priority): string
    {
        $ordered = self::PRIORITIES;
        $index = array_search($priority, $ordered, true);

        return $ordered[min($index + 1, count($ordered) - 1)] ?? 'urgent';
    }

    /**
     * Staff who can service tickets, for the assignment dropdown.
     */
    protected function agents()
    {
        return User::permission(['support.view', 'support.reply', 'support.assign'])
            ->when(
                \Illuminate\Support\Facades\Schema::hasColumn('users', 'account_status'),
                fn ($q) => $q->where(fn ($w) => $q->whereNull('account_status')->orWhere('account_status', '!=', 'suspended'))
            )
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    protected function slaBadge(SupportTicket $ticket): string
    {
        $state = $this->sla->state($ticket);

        $classes = [
            'met' => 'success',
            'on_track' => 'secondary',
            'due' => 'warning',
            'breached' => 'danger',
        ];

        $label = str_replace('_', ' ', $ticket->status === 'closed' ? 'closed' : $state);

        return '<span class="badge bg-'.($classes[$state] ?? 'secondary').'">'.ucfirst($label).'</span>';
    }
}
