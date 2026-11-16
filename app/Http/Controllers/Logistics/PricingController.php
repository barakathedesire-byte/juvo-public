<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Logistics\LogisticsDeliveryPricingRule;
use App\Models\Logistics\LogisticsZone;
use App\Models\Location;
use App\Models\Order;
use App\Services\Logistics\DeliveryPricingService;
use App\Services\Logistics\PredictiveIntelligenceService;
use Illuminate\Http\Request;

class PricingController extends Controller
{
    public function __construct(private readonly DeliveryPricingService $pricing) {}
    public function index()
    {
        $this->canManage();

        $rules = LogisticsDeliveryPricingRule::with(['zone', 'location'])->latest()->paginate(25);
        $zones = LogisticsZone::orderBy('name')->get();
        $locations = Location::with('region')->active()->ordered()->get();

        return view('logistics.pricing.index', compact('rules', 'zones', 'locations'));
    }

    public function store(Request $request)
    {
        $this->canManagePricing();
        $data = $this->validated($request);
        $data['created_by'] = auth('logistics_admin')->id();
        LogisticsDeliveryPricingRule::create($data);

        return redirect()->route('logistics.pricing.index')->with('success', 'Pricing rule created.');
    }

    public function update(Request $request, LogisticsDeliveryPricingRule $rule)
    {
        $this->canManagePricing();
        $data = $this->validated($request, $rule);
        $rule->update($data);

        return redirect()->route('logistics.pricing.index')->with('success', 'Pricing rule updated.');
    }

    public function simulate(Request $request)
    {
        $this->canManage();
        $locations = Location::with('region')->active()->ordered()->get();
        $result = null;
        if ($request->isMethod('post')) {
            $location = Location::find($request->integer('location_id'));
            $result = $this->pricing->simulate([
                'destination_location' => $location,
                'zone' => \App\Models\Logistics\LogisticsZone::whereHas('locations', fn ($q) => $q->where('locations.id', $location?->id))->first(),
                'delivery_type' => $request->string('delivery_type', 'door')->toString(),
                'vehicle_type' => $request->string('vehicle_type')->toString() ?: null,
                'distance_km' => $request->filled('distance_km') ? (float) $request->input('distance_km') : null,
                'subtotal' => (float) $request->input('subtotal', 0),
            ]);
        }

        return view('logistics.pricing.simulate', compact('locations', 'result', 'request'));
    }

    public function revenue()
    {
        $this->canManage();
        $revenue = (float) Order::sum('delivery_fee');
        $average = (float) Order::avg('delivery_fee');
        $byZone = \App\Models\ShippingAddress::query()
            ->selectRaw('state, city, COUNT(*) as orders, SUM(orders.delivery_fee) as revenue')
            ->join('orders', 'orders.id', '=', 'shipping_addresses.order_id')
            ->groupBy('state', 'city')
            ->orderByDesc('revenue')
            ->limit(20)
            ->get();
        $rules = LogisticsDeliveryPricingRule::orderBy('priority')->get();

        return view('logistics.pricing.revenue', compact('revenue', 'average', 'byZone', 'rules'));
    }

    public function approve(LogisticsDeliveryPricingRule $rule)
    {
        $this->canManagePricing();

        $admin = auth('logistics_admin')->user();
        if ($rule->created_by && (int) $rule->created_by === (int) $admin->id) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'rule' => 'A pricing rule must be approved by someone other than its creator.',
            ]);
        }

        $rule->update([
            'status' => 'active',
            'is_active' => true,
            'approved_by' => $admin->id,
            'approved_at' => now(),
            'effective_at' => $rule->effective_at ?: now(),
        ]);

        return back()->with('success', 'Pricing rule approved and activated.');
    }

    public function destroy(LogisticsDeliveryPricingRule $rule)
    {
        $this->canManagePricing();
        $rule->delete();

        return redirect()->route('logistics.pricing.index')->with('success', 'Pricing rule deleted.');
    }

    private function validated(Request $request, ?LogisticsDeliveryPricingRule $rule = null): array
    {
        $data = $request->validate([
            'zone_id' => 'nullable|exists:logistics_zones,id',
            'location_id' => 'nullable|exists:locations,id',
            'name' => 'required|string|max:255',
            'delivery_type' => 'required|string|max:32',
            'vehicle_type' => 'nullable|string|max:32',
            'base_fee' => 'required|numeric|min:0',
            'per_km_fee' => 'nullable|numeric|min:0',
            'minimum_fee' => 'nullable|numeric|min:0',
            'peak_surcharge' => 'nullable|numeric|min:0',
            'handling_surcharge' => 'nullable|numeric|min:0',
            'free_delivery_threshold' => 'nullable|numeric|min:0',
            'distance_tiers' => 'nullable|string',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'priority' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $data['per_km_fee'] = $data['per_km_fee'] ?? 0;
        $data['minimum_fee'] = $data['minimum_fee'] ?? 0;
        $data['peak_surcharge'] = $data['peak_surcharge'] ?? 0;
        $data['handling_surcharge'] = $data['handling_surcharge'] ?? 0;
        $data['priority'] = $data['priority'] ?? 100;
        $data['is_active'] = $request->boolean('is_active', true);
        $data['distance_tiers'] = $data['distance_tiers'] ? json_decode($data['distance_tiers'], true) : null;

        return $data;
    }

    private function canManage(): void
    {
        $admin = auth('logistics_admin')->user();
        abort_unless($admin && ($admin->isSuperAdmin() || $admin->canAccessLogisticsModule('zones')), 403);
    }

    private function canManagePricing(): void
    {
        $admin = auth('logistics_admin')->user();
        abort_unless($admin && ($admin->isSuperAdmin() || $admin->role === 'admin' || $admin->hasPermission('logistics.finance')), 403, 'You do not have permission to manage delivery pricing.');
    }
}
