<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Rider\Rider;
use App\Models\Logistics\DispatchConfig;
use App\Services\Logistics\DispatchRecommendationService;
use Illuminate\Http\Request;

class DispatchController extends Controller
{
    public function __construct(
        protected DispatchRecommendationService $recommendations,
    ) {}

    public function index()
    {
        $this->canDispatch();

        $config = DispatchConfig::current();
        $unassignedOrders = Order::whereNotNull('logistics_tracking_id')
            ->whereNull('assigned_driver_id')
            ->whereIn('logistics_status', ['pending', 'created', 'failed', 'returned'])
            ->with(['customer', 'pickupLocation', 'shippingAddress'])
            ->orderByRaw("CASE logistics_priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->oldest()
            ->limit(40)
            ->get();

        $riders = Rider::query()
            ->withCount(['assignedOrders as active_deliveries_count' => fn ($query) => $query->whereIn('logistics_status', ['assigned', 'accepted', 'picked_up', 'in_transit'])])
            ->where('is_active', true)
            ->where('kyc_status', 'approved')
            ->where('background_check_status', '!=', 'flagged')
            ->where(function ($query) {
                $query->whereNull('license_expiry')->orWhere('license_expiry', '>=', now()->toDateString());
            })
            ->orderByDesc('is_online')
            ->orderBy('active_deliveries_count')
            ->orderByDesc('rating')
            ->limit(40)
            ->get();

        $recommendationMap = $unassignedOrders->mapWithKeys(fn (Order $order) => [
            $order->id => $this->recommendations->recommend($order, $riders),
        ]);

        $activeAssignments = Order::whereNotNull('logistics_tracking_id')
            ->whereNotNull('assigned_driver_id')
            ->whereIn('logistics_status', ['assigned', 'accepted', 'picked_up', 'in_transit'])
            ->with(['assignedRider', 'pickupLocation', 'shippingAddress'])
            ->latest('assigned_at')
            ->limit(12)
            ->get();

        return view('logistics.dispatch.index', compact('unassignedOrders', 'riders', 'recommendationMap', 'activeAssignments', 'config'));
    }

    public function config()
    {
        $this->canDispatch();
        $config = DispatchConfig::current();

        return view('logistics.dispatch.config', compact('config'));
    }

    public function updateConfig(Request $request)
    {
        $this->canDispatch();
        $config = DispatchConfig::current();
        $data = $request->validate([
            'auto_dispatch_enabled' => 'nullable|boolean',
            'max_active_assignments' => 'required|integer|min:1|max:20',
            'assignment_timeout_minutes' => 'required|integer|min:1|max:120',
            'minimum_score' => 'required|numeric|min:0|max:100',
            'batch_size' => 'required|integer|min:1|max:50',
            'require_manual_approval' => 'nullable|boolean',
        ]);
        $data['auto_dispatch_enabled'] = $request->boolean('auto_dispatch_enabled');
        $data['require_manual_approval'] = $request->boolean('require_manual_approval');
        $config->update($data);

        return redirect()->route('logistics.dispatch.config')->with('success', 'Dispatch configuration updated.');
    }

    public function recommend(Order $order)
    {
        $this->canDispatch();
        $riders = Rider::query()
            ->withCount(['assignedOrders as active_deliveries_count' => fn ($query) => $query->whereIn('logistics_status', ['assigned', 'accepted', 'picked_up', 'in_transit'])])
            ->where('is_active', true)
            ->where('kyc_status', 'approved')
            ->get();

        return response()->json(['candidates' => $this->recommendations->recommend($order, $riders)]);
    }

    private function canDispatch(): void
    {
        $admin = auth('logistics_admin')->user();
        abort_unless($admin && ($admin->isSuperAdmin() || in_array($admin->role, ['super_admin', 'admin', 'dispatcher'], true) || $admin->canAccessLogisticsModule('dispatch')), 403);
    }
}
