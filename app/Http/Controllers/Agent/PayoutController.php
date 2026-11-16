<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentPayoutRequest;
use App\Services\Sales\AgentPayoutService;
use Illuminate\Http\Request;

class PayoutController extends Controller
{
    public function __construct(
        protected AgentPayoutService $payouts,
    ) {}

    public function index(Request $request)
    {
        $agent = $request->attributes->get('agent');

        return view('agent.payouts', [
            'balance' => $this->payouts->balance($agent),
            'payouts' => AgentPayoutRequest::where('agent_id', $agent->id)->latest('id')->paginate(15),
            'hasOpenRequest' => AgentPayoutRequest::where('agent_id', $agent->id)->open()->exists(),
        ]);
    }

    public function store(Request $request)
    {
        $agent = $request->attributes->get('agent');

        $data = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0'],
            'method' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->payouts->request($agent, $data['amount'] ?? null, array_filter([
                'method' => $data['method'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Payout requested. Our team will review it shortly.');
    }
}
