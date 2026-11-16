<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Services\Admin\ApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ApprovalController extends Controller
{
    public function __construct(protected ApprovalService $approvals) {}

    public function index(Request $request)
    {
        $status = $request->query('status', 'pending');
        $scope = $request->query('scope');

        return view('admin.approvals.index', [
            'status' => $status,
            'scope' => $scope,
            'counts' => $this->counts(),
        ]);
    }

    public function getData(Request $request)
    {
        $query = ApprovalRequest::with(['requester', 'assignee'])
            ->select('approval_requests.*');

        match ($request->query('status')) {
            'my_requests' => $query->where('requester_id', Auth::id()),
            'assigned' => $query->where('assigned_to', Auth::id()),
            'approved' => $query->where('status', 'approved'),
            'rejected' => $query->where('status', 'rejected'),
            'returned' => $query->where('status', 'changes_requested'),
            'escalated' => $query->where('status', 'escalated'),
            'all' => null,
            default => $query->open(),
        };

        return \Yajra\DataTables\Facades\DataTables::of($query)
            ->addColumn('requester_name', fn ($r) => $r->requester?->name ?? '—')
            ->addColumn('ability_label', fn ($r) => \App\Support\Access\AbilityCatalog::get($r->ability)['label'] ?? $r->ability)
            ->addColumn('amount_display', fn ($r) => $r->amount !== null ? number_format((float) $r->amount, 2) : '—')
            ->addColumn('status_badge', fn ($r) => view('admin.approvals.partials.status', ['status' => $r->status])->render())
            ->addColumn('action', fn ($r) => '<a href="'.route('admin.approvals.show', $r->id).'" class="btn btn-sm btn-primary">Review</a>')
            ->editColumn('created_at', fn ($r) => $r->created_at?->format('Y-m-d H:i'))
            ->rawColumns(['status_badge', 'action'])
            ->make(true);
    }

    public function show(ApprovalRequest $approval)
    {
        $approval->load(['requester', 'assignee', 'decider', 'steps.actor']);

        return view('admin.approvals.show', [
            'request' => $approval,
            'approvers' => User::role(['super_admin', 'admin', 'finance_manager', 'finance_approver', 'platform_admin'])->orderBy('name')->get(),
            'ability' => \App\Support\Access\AbilityCatalog::get($approval->ability),
        ]);
    }

    public function approve(Request $request, ApprovalRequest $approval)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);

        return $this->act(fn () => $this->approvals->approve(Auth::user(), $approval, $data['reason'] ?? null), 'Request approved.');
    }

    public function reject(Request $request, ApprovalRequest $approval)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        return $this->act(fn () => $this->approvals->reject(Auth::user(), $approval, $data['reason']), 'Request rejected.');
    }

    public function requestChanges(Request $request, ApprovalRequest $approval)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        return $this->act(fn () => $this->approvals->requestChanges(Auth::user(), $approval, $data['reason']), 'Changes requested.');
    }

    public function reassign(Request $request, ApprovalRequest $approval)
    {
        $data = $request->validate(['assignee_id' => ['required', 'exists:users,id']]);

        return $this->act(
            fn () => $this->approvals->reassign(Auth::user(), $approval, User::findOrFail($data['assignee_id'])),
            'Request reassigned.'
        );
    }

    public function escalate(Request $request, ApprovalRequest $approval)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);

        return $this->act(fn () => $this->approvals->escalate(Auth::user(), $approval, $data['reason'] ?? null), 'Request escalated.');
    }

    protected function act(callable $callback, string $success)
    {
        try {
            $approval = $callback();
        } catch (Throwable $e) {
            return back()->withErrors(['approval' => $e->getMessage()]);
        }

        if (request()->expectsJson()) {
            return response()->json(['status' => true, 'message' => $success, 'data' => $approval]);
        }

        return back()->with('success', $success);
    }

    protected function counts(): array
    {
        return [
            'pending' => ApprovalRequest::where('status', 'pending')->count(),
            'my_requests' => ApprovalRequest::where('requester_id', Auth::id())->count(),
            'assigned' => ApprovalRequest::where('assigned_to', Auth::id())->whereIn('status', ApprovalRequest::OPEN_STATUSES)->count(),
            'approved' => ApprovalRequest::where('status', 'approved')->count(),
            'rejected' => ApprovalRequest::where('status', 'rejected')->count(),
            'returned' => ApprovalRequest::where('status', 'changes_requested')->count(),
            'escalated' => ApprovalRequest::where('status', 'escalated')->count(),
        ];
    }
}
