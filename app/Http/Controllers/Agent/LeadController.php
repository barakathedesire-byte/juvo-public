<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerNote;
use App\Models\LeadFollowUp;
use App\Models\LeadNote;
use App\Models\LeadStatusHistory;
use App\Models\Location;
use App\Models\Product;
use App\Models\Region;
use App\Models\SalesLead;
use App\Models\SalesRep;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LeadController extends Controller
{
    protected function agent(): SalesRep
    {
        return request()->attributes->get('agent');
    }

    protected function ownedLeadsQuery(SalesRep $agent)
    {
        return SalesLead::where('sales_rep_id', $agent->id);
    }

    protected function findOwnedLead(SalesRep $agent, int $id): SalesLead
    {
        $lead = SalesLead::findOrFail($id);

        if ((int) $lead->sales_rep_id !== (int) $agent->id) {
            abort(403);
        }

        if ($lead->sales_rep_id === null) {
            $lead->update(['sales_rep_id' => $agent->id]);
        }

        return $lead;
    }

    public function index(Request $request)
    {
        $agent = $this->agent();
        $query = $this->ownedLeadsQuery($agent)
            ->with(['region', 'location', 'category', 'product']);

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($inner) use ($search) {
                $inner->where('name', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($source = $request->query('source')) {
            $query->where('source', $source);
        }

        if ($regionId = $request->query('region_id')) {
            $query->where('region_id', $regionId);
        }

        if ($locationId = $request->query('location_id')) {
            $query->where('location_id', $locationId);
        }

        if ($dateFrom = $request->query('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->query('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        if ($followUpFrom = $request->query('followup_from')) {
            $query->whereDate('next_followup_at', '>=', $followUpFrom);
        }

        if ($followUpTo = $request->query('followup_to')) {
            $query->whereDate('next_followup_at', '<=', $followUpTo);
        }

        $sort = $request->query('sort', 'latest');
        match ($sort) {
            'name' => $query->orderBy('name'),
            'followup' => $query->orderBy('next_followup_at'),
            default => $query->latest(),
        };

        $leads = $query->paginate(20)->withQueryString();
        $all = $this->ownedLeadsQuery($agent)->get();

        $metrics = [
            'total' => $all->count(),
            'new' => $all->where('status', 'new')->count(),
            'contacted' => $all->where('status', 'contacted')->count(),
            'interested' => $all->where('status', 'interested')->count(),
            'follow_up' => $all->where('status', 'follow_up')->count(),
            'converted' => $all->where('status', 'converted')->count(),
            'lost' => $all->where('status', 'lost')->count(),
            'followups_due' => $all->filter(fn (SalesLead $lead) => $lead->next_followup_at && $lead->next_followup_at->isToday())->count(),
            'overdue' => $all->filter(fn (SalesLead $lead) => $lead->next_followup_at && $lead->next_followup_at->isPast() && $lead->isOpen())->count(),
            'conversion_rate' => $all->count() > 0 ? round(($all->where('status', 'converted')->count() / $all->count()) * 100, 1) : 0,
        ];

        $pipeline = [];
        foreach (SalesLead::STATUSES as $status) {
            $pipeline[$status] = $all->where('status', $status)->values();
        }

        $regions = Region::active()->ordered()->get();
        $locations = Location::active()->ordered()->get();

        return view('agent.leads.index', compact('agent', 'leads', 'metrics', 'pipeline', 'regions', 'locations'));
    }

    public function create()
    {
        $agent = $this->agent();

        return view('agent.leads.create', [
            'agent' => $agent,
            'regions' => Region::active()->ordered()->get(),
            'locations' => Location::active()->ordered()->get(),
            'categories' => Category::orderBy('id')->get(),
            'products' => Product::visible()->with('translation')->latest()->take(100)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $agent = $this->agent();

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:30',
            'email' => 'nullable|email|max:255',
            'region_id' => 'nullable|exists:regions,id',
            'location_id' => 'nullable|exists:locations,id',
            'source' => 'required|string|max:60',
            'interest' => 'nullable|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'product_id' => 'nullable|exists:products,id',
            'needs' => 'nullable|string|max:5000',
            'budget' => 'nullable|numeric|min:0',
            'timeline' => 'nullable|string|max:60',
            'notes' => 'nullable|string|max:5000',
            'next_followup_at' => 'nullable|date',
            'referral_customer_id' => 'nullable|exists:customers,id',
            'referral_sales_rep_id' => 'nullable|exists:sales_reps,id',
        ]);

        $existing = SalesLead::where(function ($query) use ($data) {
            $query->where('phone', $data['phone']);
            if (! empty($data['email'])) {
                $query->orWhere('email', $data['email']);
            }
        })->first();

        if ($existing) {
            if ((int) $existing->sales_rep_id === (int) $agent->id) {
                return redirect()->route('agent.leads.show', $existing)
                    ->with('success', 'Lead already exists in your pipeline.');
            }

            if ($existing->sales_rep_id === null) {
                $existing->update(['sales_rep_id' => $agent->id]);

                return redirect()->route('agent.leads.show', $existing)
                    ->with('success', 'Existing lead found and assigned to you.');
            }

            return back()->withInput()->with('error', 'A lead with this phone or email already exists and is owned by another salesperson.');
        }

        $lead = SalesLead::create([
            'sales_rep_id' => $agent->id,
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'region_id' => $data['region_id'] ?? null,
            'location_id' => $data['location_id'] ?? null,
            'source' => $data['source'],
            'interest' => $data['interest'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'product_id' => $data['product_id'] ?? null,
            'needs' => $data['needs'] ?? null,
            'budget' => $data['budget'] ?? null,
            'timeline' => $data['timeline'] ?? null,
            'status' => 'new',
            'next_followup_at' => $data['next_followup_at'] ?? null,
            'referral_customer_id' => $data['referral_customer_id'] ?? null,
            'referral_sales_rep_id' => $data['referral_sales_rep_id'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        if (! empty($data['notes'])) {
            LeadNote::create([
                'sales_lead_id' => $lead->id,
                'sales_rep_id' => $agent->id,
                'note' => $data['notes'],
            ]);
        }

        LeadStatusHistory::create([
            'sales_lead_id' => $lead->id,
            'sales_rep_id' => $agent->id,
            'from_status' => null,
            'to_status' => 'new',
            'reason' => 'Lead created',
        ]);

        if (! empty($data['next_followup_at'])) {
            LeadFollowUp::create([
                'sales_lead_id' => $lead->id,
                'sales_rep_id' => $agent->id,
                'reason' => 'Initial follow-up',
                'scheduled_at' => $data['next_followup_at'],
                'status' => 'pending',
            ]);
        }

        return redirect()->route('agent.leads.show', $lead)
            ->with('success', 'Lead created successfully.');
    }

    public function show(SalesLead $lead)
    {
        $agent = $this->agent();
        $lead = $this->findOwnedLead($agent, $lead->id);
        $lead->load(['region', 'location', 'category', 'product', 'notes.salesRep', 'followUps.salesRep', 'statusHistories.salesRep', 'convertedCustomer', 'referralCustomer', 'referralSalesRep']);

        return view('agent.leads.show', compact('agent', 'lead'));
    }

    public function edit(SalesLead $lead)
    {
        $agent = $this->agent();
        $lead = $this->findOwnedLead($agent, $lead->id);

        return view('agent.leads.edit', [
            'agent' => $agent,
            'lead' => $lead,
            'regions' => Region::active()->ordered()->get(),
            'locations' => Location::active()->ordered()->get(),
            'categories' => Category::orderBy('id')->get(),
            'products' => Product::visible()->with('translation')->latest()->take(100)->get(),
        ]);
    }

    public function update(Request $request, SalesLead $lead)
    {
        $agent = $this->agent();
        $lead = $this->findOwnedLead($agent, $lead->id);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:30',
            'email' => 'nullable|email|max:255',
            'region_id' => 'nullable|exists:regions,id',
            'location_id' => 'nullable|exists:locations,id',
            'source' => 'required|string|max:60',
            'interest' => 'nullable|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'product_id' => 'nullable|exists:products,id',
            'needs' => 'nullable|string|max:5000',
            'budget' => 'nullable|numeric|min:0',
            'timeline' => 'nullable|string|max:60',
            'status' => 'nullable|string|in:new,contacted,interested,follow_up,lost',
            'lost_reason' => 'nullable|string|max:60',
            'last_contacted_at' => 'nullable|date',
            'next_followup_at' => 'nullable|date',
        ]);

        if (! empty($data['status']) && $data['status'] !== $lead->status) {
            $this->applyStatusChange($lead, $agent, $data['status'], $data['lost_reason'] ?? null);
            unset($data['status'], $data['lost_reason']);
        }

        $lead->update($data);
        $lead->update(['last_contacted_at' => $lead->last_contacted_at ?: now()]);

        return redirect()->route('agent.leads.show', $lead)
            ->with('success', 'Lead updated successfully.');
    }

    public function changeStatus(Request $request, SalesLead $lead)
    {
        $agent = $this->agent();
        $lead = $this->findOwnedLead($agent, $lead->id);

        $data = $request->validate([
            'status' => 'required|string|in:new,contacted,interested,follow_up,converted,lost,reopen',
            'lost_reason' => 'nullable|string|max:60',
        ]);

        $status = $data['status'] === 'reopen' ? 'new' : $data['status'];

        if ($status === 'lost' && empty($data['lost_reason'])) {
            return back()->withErrors(['lost_reason' => 'A reason is required when marking a lead as lost.']);
        }

        $this->changeStatusModel($lead, $agent, $status, $data['lost_reason'] ?? null);

        return back()->with('success', 'Lead status updated.');
    }

    protected function changeStatusModel(SalesLead $lead, SalesRep $agent, string $status, ?string $reason = null): void
    {
        $from = $lead->status;

        if ($status === 'lost') {
            $lead->update(['status' => 'lost', 'lost_reason' => $reason]);
        } else {
            $lead->update(['status' => $status, 'lost_reason' => null]);
        }

        LeadStatusHistory::create([
            'sales_lead_id' => $lead->id,
            'sales_rep_id' => $agent->id,
            'from_status' => $from,
            'to_status' => $status,
            'reason' => $reason,
        ]);
    }

    protected function applyStatusChange(SalesLead $lead, SalesRep $agent, string $status, ?string $reason = null): void
    {
        $this->changeStatusModel($lead, $agent, $status, $reason);
    }

    public function storeNote(Request $request, SalesLead $lead)
    {
        $agent = $this->agent();
        $lead = $this->findOwnedLead($agent, $lead->id);

        $data = $request->validate(['note' => 'required|string|max:5000']);
        LeadNote::create([
            'sales_lead_id' => $lead->id,
            'sales_rep_id' => $agent->id,
            'note' => $data['note'],
        ]);
        $lead->update(['last_contacted_at' => now()]);

        return back()->with('success', 'Note added.');
    }

    public function storeFollowUp(Request $request, SalesLead $lead)
    {
        $agent = $this->agent();
        $lead = $this->findOwnedLead($agent, $lead->id);

        $data = $request->validate([
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string|max:5000',
            'scheduled_at' => 'required|date',
        ]);

        LeadFollowUp::create([
            'sales_lead_id' => $lead->id,
            'sales_rep_id' => $agent->id,
            'reason' => $data['reason'],
            'notes' => $data['notes'] ?? null,
            'scheduled_at' => $data['scheduled_at'],
            'status' => 'pending',
        ]);

        $lead->update(['next_followup_at' => $data['scheduled_at'], 'last_contacted_at' => now()]);

        if ($agent->user) {
            try {
                app(NotificationService::class)->notifyAdmin($agent->user, 'system', [
                    'title' => 'Lead follow-up scheduled',
                    'message' => 'Follow-up for '.$lead->name.' on '.date('d M Y H:i', strtotime($data['scheduled_at'])),
                    'url' => route('agent.leads.show', $lead),
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return back()->with('success', 'Follow-up scheduled.');
    }

    public function completeFollowUp(LeadFollowUp $followUp)
    {
        $agent = $this->agent();
        $lead = $this->findOwnedLead($agent, $followUp->sales_lead_id);

        $followUp->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return back()->with('success', 'Follow-up completed.');
    }

    public function convert(SalesLead $lead)
    {
        $agent = $this->agent();
        $lead = $this->findOwnedLead($agent, $lead->id);

        if ($lead->status === 'converted' && $lead->converted_customer_id) {
            return redirect()->route('agent.customers.show', $lead->converted_customer_id)
                ->with('success', 'Lead already converted.');
        }

        $customer = DB::transaction(function () use ($lead, $agent) {
            $existing = null;

            if ($lead->phone) {
                $existing = Customer::where('phone', $lead->phone)->first();
            }

            if (! $existing && $lead->email) {
                $existing = Customer::where('email', $lead->email)->first();
            }

            if ($existing) {
                if ($existing->sales_rep_id === null) {
                    $existing->update(['sales_rep_id' => $agent->id]);
                }

                $customer = $existing;
            } else {
                $customer = Customer::create([
                    'name' => $lead->name,
                    'email' => $lead->email ?: 'lead+'.Str::uuid().'@juvo.local',
                    'password' => Hash::make(Str::random(32)),
                    'phone' => $lead->phone,
                    'address' => null,
                    'delivery_address' => null,
                    'region_id' => $lead->region_id,
                    'location_id' => $lead->location_id,
                    'sales_rep_id' => $agent->id,
                    'status' => 'active',
                    'crm_status' => 'new',
                ]);
            }

            if ($lead->notes()->exists()) {
                foreach ($lead->notes()->get() as $note) {
                    CustomerNote::create([
                        'customer_id' => $customer->id,
                        'sales_rep_id' => $note->sales_rep_id ?: $agent->id,
                        'note' => '[From lead] '.$note->note,
                    ]);
                }
            }

            $lead->update([
                'status' => 'converted',
                'converted_customer_id' => $customer->id,
                'converted_at' => now(),
                'lost_reason' => null,
            ]);

            LeadStatusHistory::create([
                'sales_lead_id' => $lead->id,
                'sales_rep_id' => $agent->id,
                'from_status' => 'follow_up',
                'to_status' => 'converted',
                'reason' => 'Converted to customer #'.$customer->id,
            ]);

            return $customer;
        });

        return redirect()->route('agent.customers.show', $customer)
            ->with('success', 'Lead converted to customer successfully.');
    }

    public function followUps(Request $request)
    {
        $agent = $this->agent();
        $filter = $request->query('filter', 'upcoming');

        $query = LeadFollowUp::where('sales_rep_id', $agent->id)->with('lead');

        if ($filter === 'today') {
            $query->whereDate('scheduled_at', today());
        } elseif ($filter === 'overdue') {
            $query->whereNull('completed_at')->where('scheduled_at', '<', now());
        } elseif ($filter === 'completed') {
            $query->whereNotNull('completed_at');
        } else {
            $query->whereNull('completed_at')->where('scheduled_at', '>=', now()->startOfDay());
        }

        $followUps = $query->orderBy('scheduled_at')->paginate(20)->withQueryString();

        return view('agent.leads.followups', compact('agent', 'followUps', 'filter'));
    }

    public function analytics(Request $request)
    {
        $agent = $this->agent();
        $dateFrom = $request->query('date_from', now()->subDays(30)->toDateString());
        $dateTo = $request->query('date_to', now()->toDateString());

        $leads = $this->ownedLeadsQuery($agent)
            ->with(['region', 'location'])
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->get();

        $converted = $leads->where('status', 'converted');
        $lost = $leads->where('status', 'lost');

        $bySource = $leads->groupBy('source')->map(function ($group) {
            return [
                'total' => $group->count(),
                'converted' => $group->where('status', 'converted')->count(),
                'rate' => $group->count() > 0 ? round(($group->where('status', 'converted')->count() / $group->count()) * 100, 1) : 0,
            ];
        });

        $byLocation = $leads->groupBy(fn (SalesLead $lead) => $lead->location?->name ?: 'Unknown')->map(function ($group) {
            return [
                'total' => $group->count(),
                'converted' => $group->where('status', 'converted')->count(),
                'rate' => $group->count() > 0 ? round(($group->where('status', 'converted')->count() / $group->count()) * 100, 1) : 0,
            ];
        });

        $byDay = $leads->groupBy(fn (SalesLead $lead) => $lead->created_at?->format('Y-m-d'))->map->count()->sortKeys();
        $byWeek = $leads->groupBy(fn (SalesLead $lead) => $lead->created_at?->format('o-\WW'))->map->count()->sortKeys();
        $byMonth = $leads->groupBy(fn (SalesLead $lead) => $lead->created_at?->format('Y-m'))->map->count()->sortKeys();

        $avgConversionDays = $converted->filter(fn (SalesLead $lead) => $lead->converted_at)
            ->map(fn (SalesLead $lead) => $lead->created_at->diffInDays($lead->converted_at))
            ->avg();

        $followUps = LeadFollowUp::where('sales_rep_id', $agent->id)
            ->whereBetween('scheduled_at', [$dateFrom.' 00:00:00', $dateTo.' 23:59:59'])
            ->get();

        $followUpCompletion = $followUps->count() > 0
            ? round(($followUps->whereNotNull('completed_at')->count() / $followUps->count()) * 100, 1)
            : 0;

        $lostReasons = $lost->groupBy('lost_reason')->map->count();

        $metrics = [
            'total' => $leads->count(),
            'converted' => $converted->count(),
            'conversion_rate' => $leads->count() > 0 ? round(($converted->count() / $leads->count()) * 100, 1) : 0,
            'avg_conversion_days' => $avgConversionDays ? round($avgConversionDays, 1) : 0,
            'followup_completion' => $followUpCompletion,
        ];

        return view('agent.leads.analytics', compact(
            'agent', 'metrics', 'bySource', 'byLocation', 'byDay', 'byWeek', 'byMonth',
            'lostReasons', 'dateFrom', 'dateTo'
        ));
    }
}

