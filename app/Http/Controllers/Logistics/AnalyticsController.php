<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Logistics\LogisticsZone;
use App\Models\Order;
use App\Models\Rider\Rider;
use App\Models\ShippingAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $this->canView();

        $from = $request->date('from') ?: now()->subDays(30)->startOfDay();
        $to = $request->date('to') ?: now()->endOfDay();
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        $base = Order::whereNotNull('logistics_tracking_id')
            ->whereBetween('created_at', [$from, $to]);

        $totalOrders = (clone $base)->count();
        $completed = (clone $base)->where('logistics_status', 'delivered')->count();
        $failed = (clone $base)->where('logistics_status', 'failed')->count();
        $cancelled = (clone $base)->where('logistics_status', 'cancelled')->count();
        $inProgress = (clone $base)->whereIn('logistics_status', ['assigned', 'accepted', 'picked_up', 'in_transit'])->count();
        $successRate = $totalOrders > 0 ? round(($completed / $totalOrders) * 100, 1) : 0;

        $avgMinutes = (clone $base)->where('logistics_status', 'delivered')
            ->whereNotNull('picked_up_at')
            ->whereNotNull('delivered_at')
            ->get(['picked_up_at', 'delivered_at'])
            ->avg(fn ($order) => $order->picked_up_at->diffInMinutes($order->delivered_at));
        $avgMinutes = $avgMinutes === null ? null : (int) round($avgMinutes);

        $deliveryRevenue = (float) (clone $base)->sum('delivery_fee');

        $codCollected = (float) \App\Models\Order::whereNotNull('cod_collected_at')
            ->whereBetween('cod_collected_at', [$from, $to])
            ->sum('cod_amount');
        $codOutstanding = (float) \App\Models\Order::whereNotNull('cod_collected_at')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('COALESCE(SUM(COALESCE(cod_amount, total_amount) - COALESCE(cod_remitted_amount, 0)),0) as amount')
            ->value('amount');
        $codReconciled = (float) \App\Models\Logistics\CodHandover::where('status', 'verified')
            ->whereBetween('verified_at', [$from, $to])
            ->sum('amount');

        $riders = Rider::count();
        $activeRiders = Rider::where('is_active', true)->count();
        $onlineRiders = Rider::where('is_online', true)->count();

        $daily = collect(range(13, 0))->map(function ($daysAgo) use ($from, $to) {
            $date = now()->subDays($daysAgo)->startOfDay();
            $query = Order::whereNotNull('logistics_tracking_id')->whereDate('created_at', $date);
            return [
                'date' => $date,
                'orders' => $query->count(),
                'completed' => (clone $query)->where('logistics_status', 'delivered')->count(),
                'failed' => (clone $query)->where('logistics_status', 'failed')->count(),
            ];
        });

        $statusBreakdown = (clone $base)
            ->selectRaw('logistics_status, COUNT(*) as count')
            ->groupBy('logistics_status')
            ->pluck('count', 'logistics_status');

        $topLocations = ShippingAddress::query()
            ->selectRaw('state, city, COUNT(*) as orders')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('state', 'city')
            ->orderByDesc('orders')
            ->limit(10)
            ->get();

        $riderPerformance = Rider::query()
            ->withCount(['assignedOrders as completed_count' => fn ($q) => $q->where('logistics_status', 'delivered')->whereBetween('delivered_at', [$from, $to])])
            ->withCount(['assignedOrders as failed_count' => fn ($q) => $q->where('logistics_status', 'failed')->whereBetween('created_at', [$from, $to])])
            ->orderByDesc('completed_count')
            ->limit(15)
            ->get();

        $zonePerformance = LogisticsZone::withCount('locations')
            ->get()
            ->map(function ($zone) use ($from, $to) {
                $locationIds = $zone->locations()->pluck('locations.id');
                $orders = Order::whereNotNull('logistics_tracking_id')
                    ->whereBetween('created_at', [$from, $to])
                    ->whereHas('shippingAddress', fn ($q) => $q->whereIn('location_id', $locationIds))
                    ->count();
                $completed = Order::whereNotNull('logistics_tracking_id')
                    ->whereBetween('created_at', [$from, $to])
                    ->where('logistics_status', 'delivered')
                    ->whereHas('shippingAddress', fn ($q) => $q->whereIn('location_id', $locationIds))
                    ->count();
                return [
                    'zone' => $zone,
                    'orders' => $orders,
                    'completed' => $completed,
                    'rate' => $orders > 0 ? round(($completed / $orders) * 100, 1) : 0,
                ];
            });

        $kpis = compact(
            'totalOrders', 'completed', 'failed', 'cancelled', 'inProgress',
            'successRate', 'avgMinutes', 'deliveryRevenue', 'codCollected',
            'codOutstanding', 'codReconciled', 'riders', 'activeRiders', 'onlineRiders'
        );

        return view('logistics.analytics.index', compact(
            'kpis', 'daily', 'statusBreakdown', 'topLocations', 'riderPerformance',
            'zonePerformance', 'from', 'to'
        ));
    }

    private function canView(): void
    {
        $admin = auth('logistics_admin')->user();
        abort_unless($admin && ($admin->isSuperAdmin() || $admin->canAccessLogisticsModule('analytics')), 403);
    }
}
