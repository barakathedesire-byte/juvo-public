<?php

namespace App\Http\Controllers\Admin\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentCommissionEntry;
use App\Models\AgentPayoutRequest;
use App\Services\Sales\AgentDashboardService;
use Illuminate\Http\Request;

class AgentReportController extends Controller
{
    public function __construct(
        protected AgentDashboardService $dashboard,
    ) {}

    public function index(Request $request)
    {
        $from = $request->input('from') ? \Illuminate\Support\Carbon::parse($request->input('from'))->startOfDay() : null;
        $to = $request->input('to') ? \Illuminate\Support\Carbon::parse($request->input('to'))->endOfDay() : null;

        return view('admin.agents.reports', [
            'summary' => $this->dashboard->networkSummary(),
            'leaderboard' => $this->dashboard->leaderboard(25, $from, $to),
            'commissionByRole' => AgentCommissionEntry::query()
                ->selectRaw('role, COUNT(*) as entries, SUM(amount) as total')
                ->whereIn('state', ['eligible', 'approved', 'payable', 'paid'])
                ->groupBy('role')
                ->get(),
            'payouts' => AgentPayoutRequest::with('agent')->latest('id')->limit(20)->get(),
            'filters' => ['from' => $request->input('from'), 'to' => $request->input('to')],
        ]);
    }
}
