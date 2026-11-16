<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentCommissionEntry;
use App\Models\AgentCommissionPolicy;
use App\Models\SupportTicket;
use App\Services\Sales\AgentCommissionEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CommissionController extends Controller
{
    public function __construct(
        protected AgentCommissionEngine $engine,
    ) {}

    public function index(Request $request)
    {
        $agent = $request->attributes->get('agent');

        $start = $request->query('from') ? \Carbon\Carbon::parse($request->query('from'))->startOfDay() : now()->startOfMonth();
        $end = $request->query('to') ? \Carbon\Carbon::parse($request->query('to'))->endOfDay() : now()->endOfMonth();

        $entries = AgentCommissionEntry::where('agent_id', $agent->id)
            ->whereBetween('created_at', [$start, $end])
            ->when($request->input('state'), fn ($q, $state) => $q->where('state', $state))
            ->when($request->input('role'), fn ($q, $role) => $q->where('role', $role))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $all = AgentCommissionEntry::where('agent_id', $agent->id)
            ->whereBetween('created_at', [$start, $end])->get();

        $metrics = [
            'total' => (float) $all->sum('amount'),
            'eligible' => (float) $all->whereIn('state', ['eligible', 'approved', 'payable'])->sum('amount'),
            'pending' => (float) $all->whereIn('state', ['pending', 'provisional'])->sum('amount'),
            'disqualified' => (float) $all->whereIn('state', ['reversed', 'held'])->sum('amount'),
            'bonuses' => (float) $all->where('role', 'bonus')->sum('amount'),
            'adjusted' => (float) $all->where('role', 'adjustment')->sum('amount'),
        ];

        $balance = $this->engine->balanceFor($agent);
        $policy = AgentCommissionPolicy::where('is_active', true)->latest('id')->first();

        $disputes = SupportTicket::where('category', 'commission_dispute')
            ->whereHas('order', fn ($q) => $q->whereIn('id', $all->pluck('order_id')))
            ->latest()->get();

        if ($request->query('export') === 'csv') {
            $filename = 'commissions-'.$start->format('Ymd').'-'.$end->format('Ymd').'.csv';
            $headers = ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="'.$filename.'"'];
            $callback = function () use ($entries) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, ['Entry ID', 'Order', 'Role', 'Basis', 'Rate', 'Amount', 'State', 'Date']);
                foreach ($entries as $entry) {
                    fputcsv($handle, [$entry->id, $entry->order_id, $entry->role, $entry->basis_amount, $entry->rate, $entry->amount, $entry->state, $entry->created_at?->format('Y-m-d')]);
                }
                fclose($handle);
            };
            return response()->stream($callback, 200, $headers);
        }

        return view('agent.commissions', [
            'entries' => $entries,
            'balance' => $balance,
            'metrics' => $metrics,
            'policy' => $policy,
            'disputes' => $disputes,
            'states' => ['pending', 'provisional', 'eligible', 'approved', 'payable', 'paid', 'held', 'reversed'],
            'roles' => ['direct', 'team', 'bonus', 'adjustment'],
            'filters' => $request->only(['state', 'role', 'from', 'to']),
            'start' => $start,
            'end' => $end,
        ]);
    }

    public function dispute(Request $request)
    {
        $agent = $request->attributes->get('agent');

        $data = $request->validate([
            'entry_id' => 'required|exists:agent_commission_entries,id',
            'category' => 'required|string|max:60',
            'description' => 'required|string|max:5000',
        ]);

        $entry = AgentCommissionEntry::where('id', $data['entry_id'])->where('agent_id', $agent->id)->firstOrFail();

        $ticket = SupportTicket::create([
            'ticket_number' => 'COM-'.strtoupper(Str::random(8)),
            'customer_id' => null,
            'vendor_id' => null,
            'order_id' => $entry->order_id,
            'subject' => 'Commission dispute: '.$data['category'],
            'category' => 'commission_dispute',
            'dispute_type' => $data['category'],
            'priority' => 'normal',
            'status' => 'open',
            'resolution_notes' => $data['description'],
        ]);

        return back()->with('success', 'Commission dispute submitted. Reference: '.$ticket->ticket_number);
    }
}
