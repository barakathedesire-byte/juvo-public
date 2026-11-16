<?php

namespace App\Http\Controllers\Admin\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentFraudReview;
use App\Services\Sales\AgentFraudService;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class AgentFraudController extends Controller
{
    public function __construct(
        protected AgentFraudService $fraud,
    ) {}

    public function index(Request $request)
    {
        return view('admin.agents.fraud', [
            'summary' => [
                'open' => AgentFraudReview::open()->count(),
                'high' => AgentFraudReview::open()->whereIn('severity', ['high', 'critical'])->count(),
                'resolved' => AgentFraudReview::whereIn('status', ['resolved', 'dismissed'])->count(),
            ],
            'filters' => $request->only(['status', 'severity']),
        ]);
    }

    public function getData(Request $request)
    {
        if (! $request->ajax()) {
            return;
        }

        $query = AgentFraudReview::with('agent')->select('agent_fraud_reviews.*');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($severity = $request->input('severity')) {
            $query->where('severity', $severity);
        }

        return DataTables::of($query)
            ->addColumn('agent_name', fn ($row) => $row->agent->name ?? '—')
            ->addColumn('severity_badge', fn ($row) => '<span class="badge bg-'.$this->severityColor($row->severity).'">'.ucfirst($row->severity).'</span>')
            ->addColumn('action', function ($row) {
                if (! in_array($row->status, ['open', 'investigating'], true)) {
                    return '—';
                }

                $clear = '<form method="POST" action="'.route('admin.sales-agents.fraud.resolve', $row->id).'" class="d-inline">'.csrf_field()
                    .'<input type="hidden" name="decision" value="clear">'
                    .'<button class="btn btn-sm btn-outline-success">Clear</button></form>';
                $confirm = '<form method="POST" action="'.route('admin.sales-agents.fraud.resolve', $row->id).'" class="d-inline">'.csrf_field()
                    .'<input type="hidden" name="decision" value="confirm">'
                    .'<button class="btn btn-sm btn-outline-danger">Confirm fraud</button></form>';

                return $clear.' '.$confirm;
            })
            ->rawColumns(['severity_badge', 'action'])
            ->make(true);
    }

    public function resolve(Request $request, AgentFraudReview $review)
    {
        $data = $request->validate([
            'decision' => ['required', 'in:clear,dismiss,confirm'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->fraud->resolve($review, auth()->id(), $data['decision'], $data['reason'] ?? null);

        return back()->with('success', 'Fraud review resolved.');
    }

    public function scan()
    {
        $count = $this->fraud->scanAll();

        return back()->with('success', "Fraud scan complete: {$count} case(s).");
    }

    protected function severityColor(string $severity): string
    {
        return match ($severity) {
            'critical' => 'dark',
            'high' => 'danger',
            'medium' => 'warning',
            default => 'secondary',
        };
    }
}
