<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentCommissionEntry;
use App\Models\AgentInvitation;
use App\Models\Customer;
use App\Models\Order;
use App\Models\SalesAttribution;
use App\Models\SalesLead;
use App\Models\SalesRep;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TeamController extends Controller
{
    protected function agent(): SalesRep
    {
        return request()->attributes->get('agent');
    }

    public function index(Request $request)
    {
        $agent = $this->agent();

        $directTeam = SalesRep::where('sponsor_id', $agent->id)->latest()->get();
        $teamIds = $directTeam->pluck('id');
        $allNetworkIds = $teamIds; // indirect not expanded unless hierarchy rules permit

        $teamOrderIds = SalesAttribution::whereIn('sales_rep_id', $teamIds)->pluck('order_id');
        $teamOrders = Order::whereIn('id', $teamOrderIds)->get();

        $metrics = [
            'direct_members' => $directTeam->count(),
            'active_members' => $directTeam->where('is_active', true)->where('status', 'approved')->count(),
            'inactive_members' => $directTeam->where('is_active', false)->count(),
            'pending_invitations' => AgentInvitation::where('inviter_sales_rep_id', $agent->id)->where('status', 'pending')->count(),
            'new_members_this_month' => $directTeam->where('created_at', '>=', now()->startOfMonth())->count(),
            'team_orders' => $teamOrders->count(),
            'team_sales_value' => (float) $teamOrders->sum('total_amount'),
            'team_customers' => Customer::whereIn('sales_rep_id', $teamIds)->count(),
            'eligible_bonuses' => (float) AgentCommissionEntry::whereIn('agent_id', $teamIds)->where('role', 'team')->whereIn('state', ['eligible', 'approved', 'payable'])->sum('amount'),
        ];

        $invitations = AgentInvitation::where('inviter_sales_rep_id', $agent->id)->latest()->paginate(10, ['*'], 'invitations_page');

        $customerReferrals = SalesLead::where('referral_sales_rep_id', $agent->id)
            ->orWhere('referral_customer_id', '!=', null)
            ->where('sales_rep_id', $agent->id)
            ->latest()->paginate(10, ['*'], 'referrals_page');

        $teamPerformance = $directTeam->map(function (SalesRep $member) {
            $orderIds = SalesAttribution::where('sales_rep_id', $member->id)->pluck('order_id');
            $orders = Order::whereIn('id', $orderIds)->get();

            return [
                'member' => $member,
                'orders' => $orders->count(),
                'sales' => (float) $orders->sum('total_amount'),
                'delivered' => $orders->whereIn('status', ['delivered', 'completed'])->count(),
                'customers' => Customer::where('sales_rep_id', $member->id)->count(),
                'leads_converted' => SalesLead::where('sales_rep_id', $member->id)->where('status', 'converted')->count(),
            ];
        });

        $rewards = AgentCommissionEntry::whereIn('agent_id', $teamIds)
            ->whereIn('role', ['team', 'bonus'])
            ->latest()->take(20)->get();

        $activity = collect()
            ->merge($directTeam->map(fn ($m) => ['type' => 'New team member', 'label' => $m->name, 'date' => $m->created_at]))
            ->merge($invitations->getCollection()->map(fn ($i) => ['type' => 'Invitation', 'label' => $i->email ?: $i->phone, 'date' => $i->created_at]))
            ->sortByDesc('date')
            ->take(20);

        $customerReferralLink = url('/ref/customer/'.$agent->code);
        $consultantInviteLink = url('/agent/register?ref='.$agent->code);

        return view('agent.team.index', compact(
            'agent', 'metrics', 'directTeam', 'invitations', 'customerReferrals',
            'teamPerformance', 'rewards', 'activity', 'customerReferralLink',
            'consultantInviteLink'
        ));
    }

    public function storeInvitation(Request $request)
    {
        $agent = $this->agent();

        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:30',
        ]);

        if (empty($data['email']) && empty($data['phone'])) {
            return back()->withErrors(['email' => 'Provide an email or phone number.']);
        }

        AgentInvitation::create([
            'inviter_sales_rep_id' => $agent->id,
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'token' => Str::random(40),
            'status' => 'pending',
            'expires_at' => now()->addDays(14),
        ]);

        return back()->with('success', 'Invitation created. Share the consultant invite link.');
    }
}
