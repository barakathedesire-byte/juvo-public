<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Services\Logistics\JuvoLogisticsService;

class TrackingController extends Controller
{
    public function __construct(private readonly JuvoLogisticsService $logistics) {}

    public function data()
    {
        $admin = auth('logistics_admin')->user();

        abort_unless($admin && $admin->canAccessLogisticsModule('tracking'), 403);

        $riders = \App\Models\Rider\Rider::query()
            ->withCount(['assignedOrders as active_deliveries_count' => fn ($query) => $query->whereIn('logistics_status', ['assigned', 'accepted', 'picked_up', 'in_transit'])])
            ->whereNotNull('current_lat')
            ->whereNotNull('current_lng')
            ->orderByDesc('is_online')
            ->get()
            ->map(fn ($rider) => [
                'id' => $rider->id,
                'name' => $rider->full_name,
                'vehicle' => $rider->vehicle_type,
                'online' => (bool) $rider->is_online,
                'active_deliveries' => (int) $rider->active_deliveries_count,
                'lat' => (float) $rider->current_lat,
                'lng' => (float) $rider->current_lng,
                'last_update' => $rider->last_online_at?->toIso8601String(),
                'stale' => $rider->is_online && $rider->active_deliveries_count > 0 && (! $rider->last_online_at || $rider->last_online_at->lt(now()->subMinutes(15))),
            ]);

        $deliveries = \App\Models\Order::whereNotNull('logistics_tracking_id')
            ->whereNotNull('assigned_driver_id')
            ->whereIn('logistics_status', ['assigned', 'accepted', 'picked_up', 'in_transit'])
            ->with(['assignedRider', 'pickupLocation', 'shippingAddress'])
            ->latest('assigned_at')
            ->limit(100)
            ->get()
            ->map(fn ($order) => [
                'id' => $order->id,
                'tracking_id' => $order->logistics_tracking_id,
                'status' => $order->logistics_status,
                'status_label' => $order->logisticsStatusLabel(),
                'rider_id' => $order->assigned_driver_id,
                'rider' => $order->assignedRider?->full_name,
                'pickup' => $order->pickupLocation?->name,
                'destination' => $order->shippingAddress?->city ?: $order->shippingAddress?->address,
                'driver_lat' => $order->driver_lat !== null ? (float) $order->driver_lat : null,
                'driver_lng' => $order->driver_lng !== null ? (float) $order->driver_lng : null,
                'assigned_at' => $order->assigned_at?->toIso8601String(),
            ]);

        return response()->json([
            'riders' => $riders,
            'deliveries' => $deliveries,
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    public function show(string $token)
    {
        return $this->render($token, false);
    }

    public function embed(string $token)
    {
        return $this->render($token, true);
    }

    private function render(string $token, bool $embed)
    {
        abort_unless($this->logistics->isEnabled(), 404);

        $tracking = $this->logistics->getTrackingByToken($token);

        abort_if($tracking === null, 404);

        return response()
            ->view('logistics.track.show', [
                'tracking' => $tracking,
                'embed' => $embed,
            ])
            ->header('Cache-Control', 'no-store, max-age=0');
    }
}
