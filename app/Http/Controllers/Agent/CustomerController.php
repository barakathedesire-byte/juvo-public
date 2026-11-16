<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerFollowUp;
use App\Models\CustomerNote;
use App\Models\Location;
use App\Models\Order;
use App\Models\Region;
use App\Models\SalesAttribution;
use App\Models\SalesRep;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    protected function agent(): SalesRep
    {
        return request()->attributes->get('agent');
    }

    protected function ownedCustomersQuery(SalesRep $agent)
    {
        $attributedCustomerIds = SalesAttribution::where('sales_rep_id', $agent->id)
            ->join('orders', 'orders.id', '=', 'sales_attributions.order_id')
            ->pluck('orders.customer_id')
            ->filter()
            ->unique()
            ->values();

        return Customer::query()
            ->where(function ($query) use ($agent, $attributedCustomerIds) {
                $query->where('sales_rep_id', $agent->id)
                    ->orWhereIn('id', $attributedCustomerIds);
            });
    }

    protected function findOwnedCustomer(SalesRep $agent, int $id): Customer
    {
        $customer = Customer::findOrFail($id);

        $owns = (int) $customer->sales_rep_id === (int) $agent->id
            || SalesAttribution::where('sales_rep_id', $agent->id)
                ->join('orders', 'orders.id', '=', 'sales_attributions.order_id')
                ->where('orders.customer_id', $customer->id)
                ->exists();

        if (! $owns) {
            abort(403);
        }

        if ($customer->sales_rep_id === null) {
            $customer->update(['sales_rep_id' => $agent->id]);
        }

        return $customer;
    }

    public function index(Request $request)
    {
        $agent = $this->agent();
        $query = $this->ownedCustomersQuery($agent)
            ->with(['region', 'location', 'orders'])
            ->withCount('orders');

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($inner) use ($search) {
                $inner->where('name', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        if ($status = $request->query('status')) {
            $query->where('crm_status', $status);
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

        $sort = $request->query('sort', 'latest');
        match ($sort) {
            'name' => $query->orderBy('name'),
            'orders' => $query->orderByDesc('orders_count'),
            'last_order' => $query->orderByDesc('last_contacted_at'),
            default => $query->latest(),
        };

        $customers = $query->paginate(20)->withQueryString();

        $all = $this->ownedCustomersQuery($agent)->withCount('orders')->get();
        $threshold = (float) config('juvo.crm.high_value_threshold', 500000);

        $metrics = [
            'total' => $all->count(),
            'new_this_month' => $all->where('created_at', '>=', now()->startOfMonth())->count(),
            'active' => $all->filter(fn (Customer $customer) => $this->segment($customer, $threshold) === 'active')->count(),
            'repeat' => $all->filter(fn (Customer $customer) => $this->segment($customer, $threshold) === 'repeat')->count(),
            'inactive' => $all->filter(fn (Customer $customer) => $this->segment($customer, $threshold) === 'inactive')->count(),
            'follow_up' => CustomerFollowUp::where('sales_rep_id', $agent->id)->whereNull('completed_at')->where('scheduled_at', '<=', now()->addDays(7))->count(),
        ];

        $segments = [
            'new' => $all->filter(fn (Customer $customer) => $this->segment($customer, $threshold) === 'new')->count(),
            'active' => $metrics['active'],
            'repeat' => $metrics['repeat'],
            'inactive' => $metrics['inactive'],
            'high_value' => $all->filter(fn (Customer $customer) => $this->segment($customer, $threshold) === 'high_value')->count(),
        ];

        $regions = Region::active()->ordered()->get();
        $locations = Location::active()->ordered()->get();

        return view('agent.customers.index', compact('agent', 'customers', 'metrics', 'segments', 'regions', 'locations', 'threshold'));
    }

    public function create()
    {
        $agent = $this->agent();

        return view('agent.customers.create', [
            'agent' => $agent,
            'regions' => Region::active()->ordered()->get(),
            'locations' => Location::active()->ordered()->get(),
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
            'delivery_address' => 'nullable|string|max:1000',
            'address' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:5000',
        ]);

        $existing = Customer::query()
            ->where(function ($query) use ($data) {
                $query->where('phone', $data['phone']);
                if (! empty($data['email'])) {
                    $query->orWhere('email', $data['email']);
                }
            })
            ->first();

        if ($existing) {
            if ($existing->sales_rep_id === null) {
                $existing->update(['sales_rep_id' => $agent->id]);
                if (! empty($data['notes'])) {
                    CustomerNote::create([
                        'customer_id' => $existing->id,
                        'sales_rep_id' => $agent->id,
                        'note' => 'Customer associated with this salesperson. '.$data['notes'],
                    ]);
                }

                return redirect()->route('agent.customers.show', $existing)
                    ->with('success', 'Existing customer found and associated with your account.');
            }

            if ((int) $existing->sales_rep_id === (int) $agent->id) {
                return redirect()->route('agent.customers.show', $existing)
                    ->with('success', 'Customer already exists in your directory.');
            }

            return back()->withInput()->with('error', 'A customer with this phone or email already exists and is managed by another salesperson.');
        }

        $email = $data['email'] ?: 'customer+'.Str::uuid().'@juvo.local';

        $customer = Customer::create([
            'name' => $data['name'],
            'email' => $email,
            'password' => Hash::make(Str::random(32)),
            'phone' => $data['phone'],
            'address' => $data['address'] ?? null,
            'delivery_address' => $data['delivery_address'] ?? null,
            'region_id' => $data['region_id'] ?? null,
            'location_id' => $data['location_id'] ?? null,
            'sales_rep_id' => $agent->id,
            'status' => 'active',
            'crm_status' => 'new',
        ]);

        if (! empty($data['notes'])) {
            CustomerNote::create([
                'customer_id' => $customer->id,
                'sales_rep_id' => $agent->id,
                'note' => $data['notes'],
            ]);
        }

        return redirect()->route('agent.customers.show', $customer)
            ->with('success', 'Customer created successfully.');
    }

    public function show(Customer $customer)
    {
        $agent = $this->agent();
        $customer = $this->findOwnedCustomer($agent, $customer->id);

        $customer->load(['region', 'location', 'orders.items.product', 'notes.salesRep', 'followUps.salesRep']);
        $threshold = (float) config('juvo.crm.high_value_threshold', 500000);

        $orders = $customer->orders()->latest()->get();
        $totalValue = (float) $orders->sum('total_amount');
        $lastOrder = $orders->sortByDesc('created_at')->first();
        $frequency = $orders->count() > 1 && $orders->sortBy('created_at')->first() && $lastOrder
            ? round($orders->count() / max(1, $orders->sortBy('created_at')->first()->created_at->diffInMonths($lastOrder->created_at) ?: 1), 2)
            : $orders->count();

        $categories = $orders->flatMap(fn (Order $order) => $order->items ?? collect())
            ->map(fn ($item) => $item->product?->category_id)
            ->filter()
            ->countBy()
            ->sortDesc()
            ->take(5);

        $insights = [
            'total_orders' => $orders->count(),
            'total_value' => $totalValue,
            'last_order_date' => $lastOrder?->created_at,
            'frequency' => $frequency,
            'top_categories' => $categories,
            'segment' => $this->segment($customer, $threshold),
        ];

        return view('agent.customers.show', compact('agent', 'customer', 'insights', 'threshold'));
    }

    public function edit(Customer $customer)
    {
        $agent = $this->agent();
        $customer = $this->findOwnedCustomer($agent, $customer->id);

        return view('agent.customers.edit', [
            'agent' => $agent,
            'customer' => $customer,
            'regions' => Region::active()->ordered()->get(),
            'locations' => Location::active()->ordered()->get(),
        ]);
    }

    public function update(Request $request, Customer $customer)
    {
        $agent = $this->agent();
        $customer = $this->findOwnedCustomer($agent, $customer->id);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:30',
            'email' => 'nullable|email|max:255',
            'region_id' => 'nullable|exists:regions,id',
            'location_id' => 'nullable|exists:locations,id',
            'delivery_address' => 'nullable|string|max:1000',
            'address' => 'nullable|string|max:1000',
            'crm_status' => 'nullable|string|max:40',
        ]);

        if (! empty($data['email']) && Customer::where('email', $data['email'])->where('id', '!=', $customer->id)->exists()) {
            return back()->withInput()->withErrors(['email' => 'This email is already used by another customer.']);
        }

        if (Customer::where('phone', $data['phone'])->where('id', '!=', $customer->id)->exists()) {
            return back()->withInput()->withErrors(['phone' => 'This phone number is already used by another customer.']);
        }

        $customer->update($data);
        $customer->update(['last_contacted_at' => now()]);

        return redirect()->route('agent.customers.show', $customer)
            ->with('success', 'Customer updated successfully.');
    }

    public function storeNote(Request $request, Customer $customer)
    {
        $agent = $this->agent();
        $customer = $this->findOwnedCustomer($agent, $customer->id);

        $data = $request->validate(['note' => 'required|string|max:5000']);
        CustomerNote::create([
            'customer_id' => $customer->id,
            'sales_rep_id' => $agent->id,
            'note' => $data['note'],
        ]);
        $customer->update(['last_contacted_at' => now()]);

        return back()->with('success', 'Note added.');
    }

    public function storeFollowUp(Request $request, Customer $customer)
    {
        $agent = $this->agent();
        $customer = $this->findOwnedCustomer($agent, $customer->id);

        $data = $request->validate([
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string|max:5000',
            'scheduled_at' => 'required|date',
        ]);

        $followUp = CustomerFollowUp::create([
            'customer_id' => $customer->id,
            'sales_rep_id' => $agent->id,
            'reason' => $data['reason'],
            'notes' => $data['notes'] ?? null,
            'scheduled_at' => $data['scheduled_at'],
            'status' => 'pending',
        ]);

        if ($agent->user) {
            try {
                app(NotificationService::class)->notifyAdmin($agent->user, 'system', [
                    'title' => 'Customer follow-up scheduled',
                    'message' => 'Follow-up for '.$customer->name.' on '.$followUp->scheduled_at->format('d M Y H:i'),
                    'url' => route('agent.customers.show', $customer),
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return back()->with('success', 'Follow-up scheduled.');
    }

    public function completeFollowUp(CustomerFollowUp $followUp)
    {
        $agent = $this->agent();
        $customer = $this->findOwnedCustomer($agent, $followUp->customer_id);

        $followUp->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return back()->with('success', 'Follow-up completed.');
    }

    public function placeOrder(Customer $customer)
    {
        $agent = $this->agent();
        $customer = $this->findOwnedCustomer($agent, $customer->id);

        return redirect()->route('agent.place-order.index', ['customer_id' => $customer->id])
            ->with('success', 'Place an order for '.$customer->name.'.');
    }

    protected function segment(Customer $customer, float $threshold): string
    {
        $orders = $customer->orders;
        $total = (float) $orders->sum('total_amount');
        $count = $orders->count();
        $last = $orders->sortByDesc('created_at')->first();

        if ($total >= $threshold) {
            return 'high_value';
        }
        if ($count >= 2) {
            return 'repeat';
        }
        if ($last && $last->created_at->gte(now()->subDays(30))) {
            return 'active';
        }
        if ($customer->created_at && $customer->created_at->gte(now()->startOfMonth())) {
            return 'new';
        }

        return 'inactive';
    }
}
