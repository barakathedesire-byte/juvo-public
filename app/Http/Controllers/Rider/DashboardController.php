<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $rider = Auth::guard('rider')->user();

        if (! $rider->isKycApproved()) {
            return redirect()->route('rider.kyc.status');
        }

        $activeStatuses = ['assigned', 'accepted', 'picked_up', 'in_transit'];
        $activeOrders = Order::where('assigned_driver_id', $rider->id)
            ->whereIn('logistics_status', $activeStatuses)
            ->with(['customer', 'pickupLocation', 'shippingAddress'])
            ->orderBy('created_at')
            ->get();

        $priority = ['in_transit' => 0, 'picked_up' => 1, 'accepted' => 2, 'assigned' => 3];
        $currentOrder = $activeOrders
            ->sortBy(fn ($order) => $priority[$order->logistics_status] ?? 9)
            ->first();
        $upcomingOrders = $activeOrders->reject(fn ($order) => $currentOrder && $order->id === $currentOrder->id)->take(5);

        $delivered = Order::where('assigned_driver_id', $rider->id)->where('logistics_status', 'delivered');

        $stats = [
            'assigned_orders' => $activeOrders->count(),
            'completed_today' => (clone $delivered)->whereDate('delivered_at', today())->count(),
            'earnings_today' => (float) (clone $delivered)->whereDate('delivered_at', today())->sum('delivery_fee'),
            'earnings_week' => (float) (clone $delivered)->whereBetween('delivered_at', [now()->startOfWeek(), now()->endOfWeek()])->sum('delivery_fee'),
            'total_earnings' => (float) (clone $delivered)->sum('delivery_fee'),
            'rating' => $rider->rating,
        ];

        $cashToCollect = $activeOrders
            ->filter(fn ($order) => in_array($order->payment_method, ['cod', 'payondelivery', 'pod', 'pay_on_delivery'], true) && ! $order->cod_collected_at)
            ->sum(fn ($order) => (float) ($order->cod_amount ?: $order->total_amount));

        $recentCompleted = Order::where('assigned_driver_id', $rider->id)
            ->where('logistics_status', 'delivered')
            ->with(['customer', 'pickupLocation'])
            ->latest('delivered_at')
            ->limit(5)
            ->get();

        $notifications = $rider->notifications()->latest()->limit(8)->get();
        $unreadCount = $rider->unreadNotifications()->count();
        $currency = $currentOrder?->currency
            ?? $activeOrders->first()?->currency
            ?? getWebConfig('default_currency', 'TZS');

        return view('rider.dashboard', compact(
            'rider', 'stats', 'currentOrder', 'upcomingOrders', 'recentCompleted',
            'cashToCollect', 'notifications', 'unreadCount', 'currency'
        ));
    }

    public function earnings(Request $request)
    {
        $rider = Auth::guard('rider')->user();

        $validated = $request->validate([
            'range' => 'nullable|in:today,7d,30d,custom',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'type' => 'nullable|in:all,delivery_earnings,bonuses,adjustments,deductions,payouts',
        ]);

        $range = $validated['range'] ?? '30d';
        $type = $validated['type'] ?? 'all';
        $today = today();

        if ($range === 'today') {
            $start = $today->copy();
            $end = $today->copy();
        } elseif ($range === '7d') {
            $start = $today->copy()->subDays(6);
            $end = $today->copy();
        } elseif ($range === 'custom' && ! empty($validated['from'])) {
            $start = \Carbon\Carbon::parse($validated['from'])->startOfDay();
            $end = ! empty($validated['to'])
                ? \Carbon\Carbon::parse($validated['to'])->endOfDay()
                : $start->copy()->endOfDay();
            $range = 'custom';
        } else {
            $range = '30d';
            $start = $today->copy()->subDays(29);
            $end = $today->copy();
        }

        $startDay = $start->copy()->startOfDay();
        $endDay = $end->copy()->endOfDay();

        $deliveredBase = Order::where('assigned_driver_id', $rider->id)
            ->where('logistics_status', 'delivered');

        $periodOrders = (clone $deliveredBase)
            ->whereBetween('delivered_at', [$startDay, $endDay]);

        $periodTotals = (clone $periodOrders)
            ->selectRaw('DATE(delivered_at) as date, SUM(delivery_fee) as total, COUNT(*) as count')
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        $chartLabels = [];
        $chartValues = [];
        $chartCounts = [];
        for ($date = $startDay->copy(); $date->lte($endDay); $date->addDay()) {
            $key = $date->toDateString();
            $row = $periodTotals->get($key);

            $chartLabels[] = $date->format('d M');
            $chartValues[] = (float) ($row->total ?? 0);
            $chartCounts[] = (int) ($row->count ?? 0);
        }

        $allTimeTotal = (float) (clone $deliveredBase)->sum('delivery_fee');
        $allTimeCount = (int) (clone $deliveredBase)->count();

        $pendingTotal = (float) Order::where('assigned_driver_id', $rider->id)
            ->whereIn('logistics_status', ['assigned', 'accepted', 'picked_up', 'in_transit'])
            ->sum('delivery_fee');

        $todayTotal = (float) (clone $deliveredBase)->whereDate('delivered_at', today())->sum('delivery_fee');
        $todayCount = (int) (clone $deliveredBase)->whereDate('delivered_at', today())->count();
        $weekTotal = (float) (clone $deliveredBase)->whereBetween('delivered_at', [now()->startOfWeek(), now()->endOfWeek()])->sum('delivery_fee');
        $weekCount = (int) (clone $deliveredBase)->whereBetween('delivered_at', [now()->startOfWeek(), now()->endOfWeek()])->count();
        $monthTotal = (float) (clone $deliveredBase)->whereMonth('delivered_at', now()->month)->whereYear('delivered_at', now()->year)->sum('delivery_fee');
        $monthCount = (int) (clone $deliveredBase)->whereMonth('delivered_at', now()->month)->whereYear('delivered_at', now()->year)->count();

        $periodTotal = (float) (clone $periodOrders)->sum('delivery_fee');
        $periodCount = (int) (clone $periodOrders)->count();
        $periodAverage = $periodCount > 0 ? $periodTotal / $periodCount : 0.0;

        $transactions = collect();
        if (in_array($type, ['all', 'delivery_earnings'], true)) {
            $transactions = (clone $periodOrders)
                ->with(['customer', 'vendor', 'pickupLocation', 'shippingAddress'])
                ->latest('delivered_at')
                ->limit(100)
                ->get();
        }

        $currency = (clone $deliveredBase)->value('currency')
            ?: getWebConfig('default_currency', 'TZS');

        $unreadCount = $rider->unreadNotifications()->count();
        $supportPhone = getWebConfig('phone_number') ?: getWebConfig('contact_phone');

        return view('rider.earnings', compact(
            'rider',
            'range',
            'type',
            'startDay',
            'endDay',
            'currency',
            'unreadCount',
            'supportPhone',
            'chartLabels',
            'chartValues',
            'chartCounts',
            'transactions',
            'allTimeTotal',
            'allTimeCount',
            'pendingTotal',
            'todayTotal',
            'todayCount',
            'weekTotal',
            'weekCount',
            'monthTotal',
            'monthCount',
            'periodTotal',
            'periodCount',
            'periodAverage'
        ));
    }

    public function orders(Request $request)
    {
        $rider = Auth::guard('rider')->user();

        $filters = [
            'all' => [],
            'new' => ['assigned'],
            'assigned' => ['accepted'],
            'in_progress' => ['picked_up', 'in_transit'],
            'completed' => ['delivered'],
            'issues' => ['failed', 'returned'],
        ];

        $filter = $request->string('filter')->toString();
        $legacyStatus = $request->string('status')->toString();
        $statuses = [];

        if (array_key_exists($filter, $filters)) {
            $statuses = $filters[$filter];
        } elseif (in_array($legacyStatus, Order::LOGISTICS_STATUSES, true)) {
            $filter = 'all';
            $statuses = [$legacyStatus];
        } else {
            $filter = 'all';
        }

        $search = trim($request->string('q')->toString());
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';

        $query = Order::where('assigned_driver_id', $rider->id)
            ->with(['customer', 'vendor', 'pickupLocation', 'shippingAddress', 'details'])
            ->when($statuses !== [], fn ($builder) => $builder->whereIn('logistics_status', $statuses))
            ->when($search !== '', function ($builder) use ($search, $like) {
                $builder->where(function ($inner) use ($search, $like) {
                    $inner->where('logistics_tracking_id', 'like', $like)
                        ->orWhere('driver_name', 'like', $like)
                        ->orWhere('pickup_location_name', 'like', $like)
                        ->orWhereHas('customer', fn ($customer) => $customer
                            ->where('name', 'like', $like)
                            ->orWhere('phone', 'like', $like)
                            ->orWhere('email', 'like', $like))
                        ->orWhereHas('shippingAddress', fn ($address) => $address
                            ->where('name', 'like', $like)
                            ->orWhere('phone', 'like', $like)
                            ->orWhere('address', 'like', $like)
                            ->orWhere('city', 'like', $like))
                        ->orWhereHas('vendor', fn ($vendor) => $vendor
                            ->where('name', 'like', $like)
                            ->orWhere('shop_name', 'like', $like));

                    if (ctype_digit($search)) {
                        $inner->orWhere('orders.id', (int) $search);
                    }
                });
            })
            ->latest('created_at');

        $orders = $query->paginate(15)->withQueryString();

        $summaryRows = Order::where('assigned_driver_id', $rider->id)
            ->selectRaw('logistics_status, COUNT(*) as count')
            ->groupBy('logistics_status')
            ->pluck('count', 'logistics_status');

        $summary = [
            'all' => (int) $summaryRows->sum(),
            'new' => (int) ($summaryRows['assigned'] ?? 0),
            'assigned' => (int) ($summaryRows['accepted'] ?? 0),
            'in_progress' => (int) $summaryRows->only(['picked_up', 'in_transit'])->sum(),
            'completed' => (int) ($summaryRows['delivered'] ?? 0),
            'issues' => (int) $summaryRows->only(['failed', 'returned'])->sum(),
        ];

        $unreadCount = $rider->unreadNotifications()->count();
        $supportPhone = getWebConfig('phone_number') ?: getWebConfig('contact_phone');

        if ($request->expectsJson()) {
            return response()->json([
                'html' => view('rider.partials.deliveries-list', compact('orders', 'search'))->render(),
                'total' => $orders->total(),
                'summary' => $summary,
                'filter' => $filter,
                'query' => $search,
            ]);
        }

        return view('rider.orders', compact(
            'rider', 'orders', 'summary', 'filter', 'search', 'filters', 'unreadCount', 'supportPhone'
        ));
    }

    public function profile()
    {
        $rider = Auth::guard('rider')->user();

        return view('rider.profile', compact('rider'));
    }

    public function toggleOnline(Request $request)
    {
        $rider = Auth::guard('rider')->user();

        if ($reason = $rider->eligibilityReason()) {
            return response()->json([
                'success' => false,
                'is_online' => $rider->is_online,
                'message' => $reason,
            ], 403);
        }

        $rider->update([
            'is_online' => ! $rider->is_online,
            'last_online_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'is_online' => $rider->is_online,
            'message' => $rider->is_online ? 'You are now online' : 'You are now offline',
        ]);
    }

    public function updateLocation(Request $request)
    {
        $rider = Auth::guard('rider')->user();

        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        $rider->update([
            'current_lat' => $request->lat,
            'current_lng' => $request->lng,
            'last_online_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    public function markNotificationsRead()
    {
        $rider = Auth::guard('rider')->user();

        $rider->unreadNotifications->markAsRead();

        return response()->json(['success' => true, 'unread_count' => 0]);
    }
}
