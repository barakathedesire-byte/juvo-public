<?php

namespace App\Http\Controllers\Admin\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentPayoutRequest;
use App\Models\SalesRep;
use App\Services\Sales\AgentPayoutService;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class AgentPayoutController extends Controller
{
    public function __construct(
        protected AgentPayoutService $payouts,
    ) {}

    public function index()
    {
        return view('admin.agents.payouts', [
            'agents' => SalesRep::whereIn('status', SalesRep::OPERATIONAL_STATUSES)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function getData(Request $request)
    {
        if (! $request->ajax()) {
            return;
        }

        $query = AgentPayoutRequest::with('agent')->select('agent_payout_requests.*');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        return DataTables::of($query)
            ->addColumn('agent_name', fn ($row) => $row->agent->name ?? '—')
            ->addColumn('status_badge', fn ($row) => '<span class="badge bg-'.$this->badge($row->status).'">'.ucfirst($row->status).'</span>')
            ->addColumn('action', function ($row) {
                $buttons = '';

                if ($row->status === 'requested') {
                    $buttons .= $this->postBtn(route('admin.sales-agents.payouts.approve', $row->id), 'Approve', 'success');
                    $buttons .= $this->postBtn(route('admin.sales-agents.payouts.reject', $row->id), 'Reject', 'danger', 'reason');
                } elseif (in_array($row->status, ['approved', 'processing'], true)) {
                    $buttons .= $this->postBtn(route('admin.sales-agents.payouts.paid', $row->id), 'Mark paid', 'primary');
                    $buttons .= $this->postBtn(route('admin.sales-agents.payouts.failed', $row->id), 'Failed', 'danger', 'reason');
                }

                return $buttons ?: '—';
            })
            ->rawColumns(['status_badge', 'action'])
            ->make(true);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'agent_id' => ['required', 'integer', 'exists:sales_reps,id'],
            'amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $agent = SalesRep::findOrFail($data['agent_id']);

        try {
            $this->payouts->request($agent, $data['amount'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Payout requested.');
    }

    public function approve(AgentPayoutRequest $payout)
    {
        try {
            $this->payouts->approve($payout, auth()->id());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Payout approved.');
    }

    public function reject(Request $request, AgentPayoutRequest $payout)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        try {
            $this->payouts->reject($payout, auth()->id(), $data['reason']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Payout rejected; entries released.');
    }

    public function paid(Request $request, AgentPayoutRequest $payout)
    {
        $data = $request->validate(['provider_reference' => ['nullable', 'string', 'max:150']]);

        try {
            $this->payouts->markPaid($payout, auth()->id(), $data['provider_reference'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Payout marked paid.');
    }

    public function failed(Request $request, AgentPayoutRequest $payout)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        try {
            $this->payouts->markFailed($payout, auth()->id(), $data['reason']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Payout marked failed; entries released.');
    }

    protected function badge(string $status): string
    {
        return match ($status) {
            'paid' => 'success',
            'approved', 'processing' => 'info',
            'rejected', 'failed' => 'danger',
            default => 'secondary',
        };
    }

    protected function postBtn(string $action, string $label, string $color, ?string $promptField = null): string
    {
        $html = '<form method="POST" action="'.$action.'" class="d-inline">'.csrf_field();
        if ($promptField) {
            $html .= '<input type="hidden" name="'.$promptField.'" value="Admin action">';
        }
        $html .= '<button class="btn btn-sm btn-outline-'.$color.'">'.$label.'</button></form>';

        return $html;
    }
}
