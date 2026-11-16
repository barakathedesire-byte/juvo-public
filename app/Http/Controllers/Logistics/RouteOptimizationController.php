<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Rider\Rider;
use App\Services\Logistics\RouteOptimizationService;
use Illuminate\Http\Request;

class RouteOptimizationController extends Controller
{
    public function __construct(protected RouteOptimizationService $optimizer) {}

    public function index()
    {
        $this->canManage();

        $plans = \App\Models\Logistics\RoutePlan::with(['rider', 'stops.order'])->latest()->paginate(20);
        $riders = Rider::where('is_active', true)->orderBy('first_name')->get();
        $orders = Order::whereNotNull('logistics_tracking_id')
            ->whereIn('logistics_status', ['assigned', 'accepted', 'picked_up', 'in_transit'])
            ->with(['pickupLocation', 'shippingAddress.location'])
            ->latest()
            ->limit(100)
            ->get();

        return view('logistics.route-optimization.index', compact('plans', 'riders', 'orders'));
    }

    public function store(Request $request)
    {
        $this->canManage();
        $data = $request->validate([
            'rider_id' => 'required|exists:riders,id',
            'order_ids' => 'required|array|min:1',
            'order_ids.*' => 'exists:orders,id',
        ]);

        $rider = Rider::findOrFail($data['rider_id']);
        $orders = Order::whereIn('id', $data['order_ids'])->get();
        $plan = $this->optimizer->optimize($rider, $orders->all());

        if (! $plan) {
            return redirect()->route('logistics.route-optimization.index')->with('error', 'No optimizable coordinates were available for the selected orders.');
        }

        return redirect()->route('logistics.route-optimization.show', $plan)->with('success', 'Route plan created.');
    }

    public function show(\App\Models\Logistics\RoutePlan $plan)
    {
        $this->canManage();
        $plan->load(['rider', 'stops.order', 'stops.location']);

        return view('logistics.route-optimization.show', compact('plan'));
    }

    private function canManage(): void
    {
        $admin = auth('logistics_admin')->user();
        abort_unless($admin && ($admin->isSuperAdmin() || $admin->canAccessLogisticsModule('dispatch')), 403);
    }
}
