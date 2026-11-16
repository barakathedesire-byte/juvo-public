<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\SalesAttribution;
use App\Models\SalesLead;
use App\Services\Sales\AgentAttributionService;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function __construct(
        protected AgentAttributionService $attributions,
    ) {}

    public function index(Request $request)
    {
        $agent = $request->attributes->get('agent');

        return view('agent.referrals', [
            'link' => url('/ref/'.$agent->code),
            'code' => $agent->code,
            'leads' => SalesLead::where('sales_rep_id', $agent->id)->latest('id')->paginate(15, ['*'], 'leads_page'),
            'attributions' => SalesAttribution::where('sales_rep_id', $agent->id)->with('order')->latest('id')->paginate(15, ['*'], 'attr_page'),
            'leadStats' => [
                'total' => SalesLead::where('sales_rep_id', $agent->id)->count(),
                'open' => SalesLead::where('sales_rep_id', $agent->id)->open()->count(),
                'won' => SalesLead::where('sales_rep_id', $agent->id)->where('status', 'won')->count(),
            ],
        ]);
    }

    public function confirm(Request $request, SalesAttribution $attribution)
    {
        $agent = $request->attributes->get('agent');
        abort_unless($attribution->sales_rep_id === $agent->id, 403);

        $this->attributions->confirm($attribution, 'agent');

        return back()->with('success', 'Attribution confirmed.');
    }

    public function dispute(Request $request, SalesAttribution $attribution)
    {
        $agent = $request->attributes->get('agent');
        abort_unless($attribution->sales_rep_id === $agent->id, 403);

        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $this->attributions->dispute($attribution, $data['reason'], $agent->user_id);

        return back()->with('success', 'Dispute submitted for review.');
    }
}
