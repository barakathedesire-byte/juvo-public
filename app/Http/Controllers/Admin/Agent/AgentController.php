<?php

namespace App\Http\Controllers\Admin\Agent;

use App\Http\Controllers\Controller;
use App\Models\Region;
use App\Models\SalesRep;
use App\Services\Sales\AgentApprovalService;
use App\Services\Sales\AgentDashboardService;
use App\Services\Sales\AgentRegistrationService;
use App\Services\Sales\CommissionPolicyService;
use App\Services\Admin\AuditLogger;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class AgentController extends Controller
{
    public function __construct(
        protected AgentDashboardService $dashboard,
        protected AgentRegistrationService $registration,
        protected AgentApprovalService $approvals,
        protected CommissionPolicyService $policies,
        protected AuditLogger $audit,
    ) {}

    public function index()
    {
        return view('admin.agents.index', [
            'summary' => $this->dashboard->networkSummary(),
            'leaderboard' => $this->dashboard->leaderboard(10),
        ]);
    }

    public function getData(Request $request)
    {
        if (! $request->ajax()) {
            return;
        }

        $query = SalesRep::query()
            ->with('sponsor')
            ->withCount(['attributions', 'commissionEntries'])
            ->select('sales_reps.*');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        return DataTables::of($query)
            ->addColumn('status_badge', fn ($row) => $this->badge($row->status))
            ->addColumn('verified', fn ($row) => $row->verification_status === 'verified'
                ? '<span class="badge bg-success">Verified</span>'
                : '<span class="badge bg-secondary">'.ucfirst($row->verification_status).'</span>')
            ->addColumn('sponsor_name', fn ($row) => $row->sponsor->name ?? '—')
            ->addColumn('action', fn ($row) => '<a href="'.route('admin.sales-agents.show', $row->id).'" class="btn btn-sm btn-outline-dark">Manage</a>')
            ->rawColumns(['status_badge', 'verified', 'action'])
            ->make(true);
    }

    public function show(SalesRep $agent)
    {
        $agent->load(['user', 'region', 'location', 'sponsor', 'sponsor', 'commissionEntries' => fn ($q) => $q->latest('id')->limit(25), 'payoutRequests' => fn ($q) => $q->latest('id')->limit(10), 'fraudReviews', 'statusHistories.actor']);

        return view('admin.agents.show', [
            'agent' => $agent,
            'balance' => app(\App\Services\Sales\AgentCommissionEngine::class)->balanceFor($agent),
            'policy' => $this->policies->activePolicy(),
        ]);
    }

    public function create()
    {
        return view('admin.agents.create', [
            'agent' => new SalesRep(['status' => 'applied', 'currency' => 'TZS']),
            'regions' => Region::active()->ordered()->get(),
            'agents' => SalesRep::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $agent = $this->registration->apply($data, auth()->id());

        return redirect()->route('admin.sales-agents.show', $agent->id)->with('success', 'Agent created.');
    }

    public function edit(SalesRep $agent)
    {
        return view('admin.agents.edit', [
            'agent' => $agent,
            'regions' => Region::active()->ordered()->get(),
            'agents' => SalesRep::where('id', '!=', $agent->id)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, SalesRep $agent)
    {
        $data = $this->validated($request, $agent);
        $before = $agent->only(array_keys($data));

        $agent->update($data);

        $this->audit->log('agent.updated', $agent, $before, $agent->only(array_keys($data)));

        return redirect()->route('admin.sales-agents.show', $agent->id)->with('success', 'Agent updated.');
    }

    public function verify(Request $request, SalesRep $agent)
    {
        $data = $request->validate(['passed' => ['required', 'boolean'], 'notes' => ['nullable', 'string', 'max:500']]);

        $this->approvals->verify($agent, auth()->id(), (bool) $data['passed'], $data['notes'] ?? null);

        return back()->with('success', 'Verification recorded.');
    }

    public function submitForVerification(SalesRep $agent)
    {
        $this->approvals->submitForVerification($agent, auth()->id());

        return back()->with('success', 'Moved to verification.');
    }

    public function startReview(SalesRep $agent)
    {
        $this->approvals->startReview($agent, auth()->id());

        return back()->with('success', 'Moved to review.');
    }

    public function approve(SalesRep $agent)
    {
        try {
            $this->approvals->approve($agent, auth()->id());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Agent approved.');
    }

    public function reject(Request $request, SalesRep $agent)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        try {
            $this->approvals->reject($agent, auth()->id(), $data['reason']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Agent rejected.');
    }

    public function suspend(Request $request, SalesRep $agent)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $this->approvals->suspend($agent, auth()->id(), $data['reason']);

        return back()->with('success', 'Agent suspended.');
    }

    public function reinstate(SalesRep $agent)
    {
        try {
            $this->approvals->reinstate($agent, auth()->id());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Agent reinstated.');
    }

    public function close(SalesRep $agent)
    {
        $this->approvals->close($agent, auth()->id());

        return back()->with('success', 'Agent closed.');
    }

    public function payoutAccount(Request $request, SalesRep $agent)
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,verified,rejected'],
        ]);

        $agent->update(['payout_status' => $data['status']]);

        $this->audit->log('agent.payout_account.'.$data['status'], $agent, [], ['payout_status' => $data['status']]);

        return back()->with('success', 'Payout account status updated.');
    }

    public function destroy(SalesRep $agent)
    {
        $before = $agent->only(['name', 'code', 'status']);
        $agent->delete();
        $this->audit->log('agent.deleted', $agent, $before, []);

        return redirect()->route('admin.sales-agents.index')->with('success', 'Agent removed.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?SalesRep $agent = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'code' => ['nullable', 'string', 'max:50', 'alpha_dash', 'unique:sales_reps,code'.($agent ? ','.$agent->id : '')],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'region_id' => ['nullable', 'integer', 'exists:regions,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'sponsor_id' => ['nullable', 'integer', 'exists:sales_reps,id', $agent ? 'not_in:'.$agent->id : 'nullable'],
            'commission_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'monthly_target' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'payout_method' => ['nullable', 'string', 'max:50'],
            'payout_account_name' => ['nullable', 'string', 'max:150'],
            'payout_account_number' => ['nullable', 'string', 'max:150'],
            'customer_confirmation_required' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $data['monthly_target'] = $data['monthly_target'] ?? 0;
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $data['customer_confirmation_required'] = (bool) ($data['customer_confirmation_required'] ?? true);
        if (! empty($data['payout_method'])) {
            $data['payout_status'] = 'pending';
        }

        return $data;
    }

    protected function badge(string $status): string
    {
        $map = [
            'active' => 'success',
            'approved' => 'success',
            'applied' => 'secondary',
            'verification' => 'info',
            'review' => 'info',
            'suspended' => 'warning',
            'rejected' => 'danger',
            'closed' => 'dark',
            'draft' => 'secondary',
        ];

        return '<span class="badge bg-'.($map[$status] ?? 'secondary').'">'.ucfirst($status).'</span>';
    }
}
