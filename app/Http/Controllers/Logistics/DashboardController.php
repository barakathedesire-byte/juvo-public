<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Logistics\DeliveryEvent;
use App\Models\Order;
use App\Models\Rider\Rider;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $period = in_array($request->string('period')->toString(), ['today', '7', '30'], true)
            ? $request->string('period')->toString()
            : 'today';
        $days = match ($period) {
            '7' => 7,
            '30' => 30,
            default => 1,
        };
        $since = now()->subDays($days - 1)->startOfDay();
        $base = Order::whereNotNull('logistics_tracking_id');

        $stats = [
            'total_orders' => (clone $base)->count(),
            'pending_dispatch' => (clone $base)->where('logistics_status', 'pending')->count(),
            'in_transit' => (clone $base)->whereIn('logistics_status', ['assigned', 'accepted', 'picked_up', 'in_transit'])->count(),
            'delivered_period' => (clone $base)->where('logistics_status', 'delivered')
                ->where('delivered_at', '>=', $since)->count(),
            'active_riders' => Rider::where('is_online', true)->where('is_active', true)->count(),
            'pending_kyc' => Rider::whereIn('kyc_status', ['pending', 'under_review'])->count(),
            'failed_period' => (clone $base)->whereIn('logistics_status', ['failed', 'returned'])
                ->where('updated_at', '>=', $since)->count(),
            'unassigned' => (clone $base)->whereNull('assigned_driver_id')
                ->whereIn('logistics_status', ['pending', 'created'])->count(),
        ];

        $stats['delivery_rate'] = $stats['delivered_period'] + $stats['failed_period'] > 0
            ? round(($stats['delivered_period'] / ($stats['delivered_period'] + $stats['failed_period'])) * 100)
            : null;

        $recentOrders = (clone $base)
            ->with(['customer', 'vendor', 'pickupLocation', 'assignedRider', 'shippingAddress'])
            ->latest()
            ->limit(8)
            ->get();

        $dispatchQueue = (clone $base)
            ->whereIn('logistics_status', ['pending', 'created', 'failed', 'returned'])
            ->with(['customer', 'pickupLocation', 'shippingAddress'])
            ->oldest()
            ->limit(6)
            ->get();

        $exceptionOrders = (clone $base)
            ->whereIn('logistics_status', ['failed', 'returned', 'cancelled'])
            ->with(['customer', 'assignedRider'])
            ->latest('updated_at')
            ->limit(5)
            ->get();

        $riderSnapshot = Rider::withCount(['assignedOrders as active_deliveries_count' => fn ($query) => $query->whereIn('logistics_status', ['assigned', 'accepted', 'picked_up', 'in_transit'])])
            ->where('is_active', true)
            ->orderByDesc('is_online')
            ->orderByDesc('total_deliveries')
            ->limit(6)
            ->get();

        $activity = DeliveryEvent::with(['order', 'rider'])->latest()->limit(8)->get();

        $cash = [
            'awaiting' => (float) (clone $base)->where('logistics_status', 'delivered')
                ->whereNull('cod_collected_at')
                ->whereIn('payment_method', ['cod', 'payondelivery', 'pod', 'pay_on_delivery'])
                ->sum('total_amount'),
            'awaiting_count' => (clone $base)->where('logistics_status', 'delivered')
                ->whereNull('cod_collected_at')
                ->whereIn('payment_method', ['cod', 'payondelivery', 'pod', 'pay_on_delivery'])
                ->count(),
            'outstanding' => (float) (clone $base)->where('logistics_status', 'delivered')
                ->whereNotNull('cod_collected_at')
                ->whereRaw('(COALESCE(cod_amount, total_amount) - COALESCE(cod_remitted_amount, 0)) > 0')
                ->selectRaw('COALESCE(SUM(COALESCE(cod_amount, total_amount) - COALESCE(cod_remitted_amount, 0)),0) as amount')
                ->value('amount'),
        ];

        $deliveryDurations = (clone $base)->where('logistics_status', 'delivered')
            ->whereNotNull('picked_up_at')
            ->whereNotNull('delivered_at')
            ->where('delivered_at', '>=', now()->subDays(30))
            ->get(['picked_up_at', 'delivered_at']);
        $avgMinutes = $deliveryDurations->isEmpty()
            ? null
            : (int) round($deliveryDurations->avg(fn ($order) => $order->picked_up_at->diffInMinutes($order->delivered_at)));

        $deliveryStats = collect(range(6, 0))->map(function ($daysAgo) use ($base) {
            $date = now()->subDays($daysAgo)->startOfDay();

            return [
                'date' => $date,
                'count' => (clone $base)->whereDate('created_at', $date)->count(),
            ];
        });

        $overdueOrders = (clone $base)->whereIn('logistics_status', ['pending', 'created', 'assigned', 'accepted', 'picked_up', 'in_transit'])
            ->where(function ($query) {
                $query->whereNotNull('scheduled_for')->where('scheduled_for', '<', now()->startOfDay())
                    ->orWhereNull('scheduled_for')->where('created_at', '<', now()->subDay());
            })
            ->count();

        $revenuePeriod = (float) (clone $base)->where('logistics_status', 'delivered')
            ->where('delivered_at', '>=', $since)
            ->sum('total_amount');

        $availableRiders = Rider::where('is_active', true)
            ->where('is_online', true)
            ->whereDoesntHave('assignedOrders', fn ($query) => $query->whereIn('logistics_status', ['assigned', 'accepted', 'picked_up', 'in_transit']))
            ->count();
        $busyRiders = Rider::where('is_active', true)
            ->where('is_online', true)
            ->whereHas('assignedOrders', fn ($query) => $query->whereIn('logistics_status', ['assigned', 'accepted', 'picked_up', 'in_transit']))
            ->count();

        $periodLabel = match ($period) {
            '7' => 'Last 7 days',
            '30' => 'Last 30 days',
            default => 'Today',
        };

        return view('logistics.dashboard', compact(
            'stats', 'recentOrders', 'dispatchQueue', 'exceptionOrders', 'riderSnapshot',
            'activity', 'cash', 'avgMinutes', 'deliveryStats', 'period', 'periodLabel',
            'overdueOrders', 'revenuePeriod', 'availableRiders', 'busyRiders'
        ));
    }

    public function orders(Request $request)
    {
        $status = $request->string('status')->toString();
        $search = $request->string('q')->toString();
        $assignment = $request->string('assignment')->toString();
        $location = $request->string('location')->toString();
        $riderId = $request->string('rider')->toString();
        $priority = $request->string('priority')->toString();
        $dateFrom = $request->string('date_from')->toString();
        $dateTo = $request->string('date_to')->toString();
        $sort = $request->string('sort')->toString();

        $query = Order::whereNotNull('logistics_tracking_id')
            ->with(['customer', 'vendor', 'assignedRider', 'pickupLocation', 'shippingAddress'])
            ->when($status !== '', fn ($builder) => $builder->where('logistics_status', $status))
            ->when($assignment === 'unassigned', fn ($builder) => $builder->whereNull('assigned_driver_id'))
            ->when($assignment === 'assigned', fn ($builder) => $builder->whereNotNull('assigned_driver_id'))
            ->when($location !== '', fn ($builder) => $builder->where('pickup_location_id', $location))
            ->when($riderId !== '', fn ($builder) => $builder->where('assigned_driver_id', $riderId))
            ->when($priority !== '', fn ($builder) => $builder->where('logistics_priority', $priority))
            ->when($dateFrom !== '', fn ($builder) => $builder->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($builder) => $builder->whereDate('created_at', '<=', $dateTo))
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($inner) use ($search) {
                    $inner->where('logistics_tracking_id', 'like', "%{$search}%");

                    if (ctype_digit($search)) {
                        $inner->orWhere('id', (int) $search);
                    }
                });
            });

        match ($sort) {
            'oldest' => $query->oldest(),
            'amount' => $query->orderByDesc('total_amount'),
            'delivered' => $query->orderByDesc('delivered_at'),
            default => $query->latest(),
        };

        $orders = $query->paginate(20)->withQueryString();

        $statuses = ['pending', 'created', 'assigned', 'accepted', 'picked_up', 'in_transit', 'delivered', 'failed', 'returned', 'cancelled'];
        $riders = Rider::orderBy('first_name')->get(['id', 'first_name', 'last_name']);
        $locations = Location::orderBy('name')->get(['id', 'name']);

        $summaryRows = Order::whereNotNull('logistics_tracking_id')
            ->selectRaw('logistics_status, COUNT(*) as count')
            ->groupBy('logistics_status')
            ->pluck('count', 'logistics_status');
        $summary = [
            'all' => (int) $summaryRows->sum(),
            'active' => (int) $summaryRows->only(['assigned', 'accepted', 'picked_up', 'in_transit'])->sum(),
            'pending' => (int) ($summaryRows['pending'] ?? 0) + (int) ($summaryRows['created'] ?? 0),
            'delivered' => (int) ($summaryRows['delivered'] ?? 0),
            'exceptions' => (int) $summaryRows->only(['failed', 'returned'])->sum(),
            'unassigned' => Order::whereNotNull('logistics_tracking_id')->whereNull('assigned_driver_id')
                ->whereIn('logistics_status', ['pending', 'created'])->count(),
        ];

        return view('logistics.orders.index', compact(
            'orders', 'statuses', 'status', 'search', 'assignment', 'location', 'riderId',
            'priority', 'dateFrom', 'dateTo', 'sort', 'riders', 'locations', 'summary'
        ));
    }

    public function riders(Request $request)
    {
        $kyc = $request->string('kyc')->toString();
        $search = $request->string('q')->toString();
        $availability = $request->string('availability')->toString();
        $sort = $request->string('sort')->toString();

        $query = Rider::query()
            ->withCount(['assignedOrders as active_deliveries_count' => fn ($builder) => $builder->whereIn('logistics_status', ['assigned', 'accepted', 'picked_up', 'in_transit'])])
            ->when($kyc !== '', fn ($builder) => $builder->where('kyc_status', $kyc))
            ->when($availability === 'online', fn ($builder) => $builder->where('is_online', true))
            ->when($availability === 'offline', fn ($builder) => $builder->where('is_online', false))
            ->when($availability === 'suspended', fn ($builder) => $builder->where('is_active', false))
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($inner) use ($search) {
                    $inner->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            });

        match ($sort) {
            'deliveries' => $query->orderByDesc('total_deliveries'),
            'rating' => $query->orderByDesc('rating'),
            'online' => $query->orderByDesc('is_online')->orderByDesc('last_online_at'),
            default => $query->latest(),
        };

        $riders = $query->paginate(20)->withQueryString();

        $kycStatuses = ['pending', 'under_review', 'approved', 'rejected'];
        $summary = [
            'total' => Rider::count(),
            'online' => Rider::where('is_online', true)->where('is_active', true)->count(),
            'eligible' => Rider::where('is_active', true)->where('kyc_status', 'approved')
                ->where('background_check_status', '!=', 'flagged')->count(),
            'pending_kyc' => Rider::whereIn('kyc_status', ['pending', 'under_review'])->count(),
            'suspended' => Rider::where('is_active', false)->count(),
        ];

        return view('logistics.riders.index', compact(
            'riders', 'kycStatuses', 'kyc', 'search', 'availability', 'sort', 'summary'
        ));
    }

    public function zones()
    {
        $locations = Location::with('region')->orderBy('name')->get();

        return view('logistics.zones.index', compact('locations'));
    }

    public function analytics()
    {
        $base = Order::whereNotNull('logistics_tracking_id');

        $byStatus = (clone $base)
            ->selectRaw('logistics_status, COUNT(*) as count')
            ->groupBy('logistics_status')
            ->pluck('count', 'logistics_status');

        $topRiders = Rider::where('total_deliveries', '>', 0)
            ->orderByDesc('total_deliveries')
            ->limit(10)
            ->get();

        $totals = [
            'orders' => (clone $base)->count(),
            'delivered' => (int) ($byStatus['delivered'] ?? 0),
            'failed' => (int) ($byStatus['failed'] ?? 0),
            'riders' => Rider::count(),
            'active_riders' => Rider::where('is_active', true)->count(),
        ];

        $deliveryDurations = (clone $base)->where('logistics_status', 'delivered')
            ->whereNotNull('picked_up_at')
            ->whereNotNull('delivered_at')
            ->where('delivered_at', '>=', now()->subDays(30))
            ->get(['picked_up_at', 'delivered_at']);
        $avgMinutes = $deliveryDurations->isEmpty()
            ? null
            : (int) round($deliveryDurations->avg(fn ($order) => $order->picked_up_at->diffInMinutes($order->delivered_at)));

        $deliveryStats = collect(range(6, 0))->map(function ($daysAgo) use ($base) {
            $date = now()->subDays($daysAgo)->startOfDay();

            return [
                'date' => $date,
                'count' => (clone $base)->whereDate('created_at', $date)->count(),
                'pending_cod' => (clone $base)->where('logistics_status', 'delivered')
                    ->whereDate('delivered_at', $date)
                    ->whereNull('cod_collected_at')
                    ->whereIn('payment_method', ['cod', 'payondelivery', 'pod', 'pay_on_delivery'])
                    ->count(),
            ];
        });

        $cash = [
            'awaiting_count' => (clone $base)->where('logistics_status', 'delivered')
                ->whereNull('cod_collected_at')
                ->whereIn('payment_method', ['cod', 'payondelivery', 'pod', 'pay_on_delivery'])
                ->count(),
        ];

        return view('logistics.analytics.index', compact(
            'byStatus', 'topRiders', 'totals', 'avgMinutes', 'deliveryStats', 'cash'
        ));
    }

    public function settings()
    {
        $settings = [
            'Logistics enabled' => config('juvo-logistics.enabled') ? 'Yes' : 'No',
            'API base URL' => config('juvo-logistics.api.base_url') ?: 'Not configured',
            'Tracking URL' => config('juvo-logistics.tracking.public_tracking_url') ?: 'Not configured',
            'Admin registration open' => config('juvo-logistics.registration_open') ? 'Yes' : 'No',
            'Webhook secret' => config('juvo-logistics.webhook.secret') ? 'Configured' : 'Not configured',
        ];

        $counts = [
            'admins' => \App\Models\Logistics\LogisticsAdmin::count(),
            'active_admins' => \App\Models\Logistics\LogisticsAdmin::where('is_active', true)->count(),
            'riders' => Rider::count(),
        ];

        return view('logistics.settings.index', compact('settings', 'counts'));
    }

    public function profile()
    {
        $admin = auth('logistics_admin')->user();

        return view('logistics.profile', compact('admin'));
    }

    private function authorizeLogisticsModule(string $module): void
    {
        $admin = auth('logistics_admin')->user();

        abort_unless($admin && $admin->canAccessLogisticsModule($module), 403);
    }

    public function exportOrders(Request $request)
    {
        $this->authorizeLogisticsModule('orders');

        $status = $request->string('status')->toString();
        $search = $request->string('q')->toString();
        $assignment = $request->string('assignment')->toString();
        $priority = $request->string('priority')->toString();
        $dateFrom = $request->string('date_from')->toString();
        $dateTo = $request->string('date_to')->toString();

        $orders = Order::whereNotNull('logistics_tracking_id')
            ->with(['customer', 'assignedRider', 'pickupLocation', 'shippingAddress'])
            ->when($status !== '', fn ($builder) => $builder->where('logistics_status', $status))
            ->when($assignment === 'unassigned', fn ($builder) => $builder->whereNull('assigned_driver_id'))
            ->when($assignment === 'assigned', fn ($builder) => $builder->whereNotNull('assigned_driver_id'))
            ->when($priority !== '', fn ($builder) => $builder->where('logistics_priority', $priority))
            ->when($dateFrom !== '', fn ($builder) => $builder->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($builder) => $builder->whereDate('created_at', '<=', $dateTo))
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($inner) use ($search) {
                    $inner->where('logistics_tracking_id', 'like', "%{$search}%");
                    if (ctype_digit($search)) {
                        $inner->orWhere('id', (int) $search);
                    }
                });
            })
            ->latest()
            ->get();

        $filename = 'juvo-logistics-orders-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($orders) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Order ID', 'Tracking ID', 'Customer', 'Rider', 'Status', 'Priority', 'Pickup', 'Destination', 'Amount', 'Currency', 'Payment', 'Created']);

            foreach ($orders as $order) {
                fputcsv($handle, [
                    $order->id,
                    $order->logistics_tracking_id,
                    trim(($order->customer->first_name ?? '').' '.($order->customer->last_name ?? '')) ?: 'Guest',
                    $order->assignedRider?->full_name,
                    $order->logisticsStatusLabel(),
                    $order->logistics_priority ?: 'normal',
                    $order->pickupLocation?->name,
                    $order->shippingAddress?->city ?: $order->shippingAddress?->address,
                    $order->total_amount,
                    $order->currency,
                    $order->payment_method,
                    $order->created_at?->toDateTimeString(),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function dispatch()
    {
        $this->authorizeLogisticsModule('dispatch');

        $unassignedOrders = Order::whereNotNull('logistics_tracking_id')
            ->whereNull('assigned_driver_id')
            ->whereIn('logistics_status', ['pending', 'created', 'failed', 'returned'])
            ->with(['customer', 'pickupLocation', 'shippingAddress'])
            ->orderByRaw("CASE logistics_priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->oldest()
            ->limit(40)
            ->get();

        $availableRiders = Rider::query()
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

        $recommendations = $unassignedOrders->mapWithKeys(function (Order $order) use ($availableRiders) {
            return [$order->id => $this->recommendedRider($order, $availableRiders)?->id];
        });

        $activeAssignments = Order::whereNotNull('logistics_tracking_id')
            ->whereNotNull('assigned_driver_id')
            ->whereIn('logistics_status', ['assigned', 'accepted', 'picked_up', 'in_transit'])
            ->with(['assignedRider', 'pickupLocation', 'shippingAddress'])
            ->latest('assigned_at')
            ->limit(12)
            ->get();

        $dispatchEvents = DeliveryEvent::with(['order', 'rider'])->latest()->limit(15)->get();

        return view('logistics.dispatch.index', compact(
            'unassignedOrders', 'availableRiders', 'recommendations', 'activeAssignments', 'dispatchEvents'
        ));
    }

    public function tracking()
    {
        $this->authorizeLogisticsModule('tracking');

        $riders = Rider::query()
            ->withCount(['assignedOrders as active_deliveries_count' => fn ($query) => $query->whereIn('logistics_status', ['assigned', 'accepted', 'picked_up', 'in_transit'])])
            ->whereNotNull('current_lat')
            ->whereNotNull('current_lng')
            ->orderByDesc('is_online')
            ->orderByDesc('last_online_at')
            ->limit(100)
            ->get();

        $activeDeliveries = Order::whereNotNull('logistics_tracking_id')
            ->whereNotNull('assigned_driver_id')
            ->whereIn('logistics_status', ['assigned', 'accepted', 'picked_up', 'in_transit'])
            ->with(['assignedRider', 'pickupLocation', 'shippingAddress'])
            ->latest('assigned_at')
            ->limit(50)
            ->get();

        $staleRiders = $riders->filter(function (Rider $rider) {
            return $rider->is_online
                && $rider->active_deliveries_count > 0
                && (! $rider->last_online_at || $rider->last_online_at->lt(now()->subMinutes(15)));
        })->values();

        return view('logistics.tracking.index', compact('riders', 'activeDeliveries', 'staleRiders'));
    }

    private function recommendedRider(Order $order, $riders): ?Rider
    {
        $destinationCity = strtolower(trim((string) ($order->shippingAddress?->city ?? '')));
        $destinationState = strtolower(trim((string) ($order->shippingAddress?->state ?? '')));
        $pickupName = strtolower(trim((string) ($order->pickupLocation?->name ?? '')));

        $candidates = $riders->filter(fn (Rider $rider) => $rider->isEligibleForWork());

        if ($candidates->isEmpty()) {
            return null;
        }

        return $candidates->sortByDesc(function (Rider $rider) use ($destinationCity, $destinationState, $pickupName) {
            $score = 0;

            if ($rider->is_online) {
                $score += 40;
            }

            $score += max(0, 30 - ($rider->active_deliveries_count * 10));

            $riderCity = strtolower(trim((string) $rider->city));
            $riderState = strtolower(trim((string) $rider->state));

            if ($destinationCity !== '' && $riderCity === $destinationCity) {
                $score += 20;
            }

            if ($destinationState !== '' && $riderState === $destinationState) {
                $score += 10;
            }

            if ($pickupName !== '' && $riderCity !== '' && str_contains($pickupName, $riderCity)) {
                $score += 8;
            }

            $score += (int) round((float) $rider->rating * 2);

            if ($rider->license_expiry && $rider->license_expiry->isFuture()) {
                $score += 5;
            }

            return $score;
        })->sortBy('active_deliveries_count')->first();
    }

    public function support()
    {
        $this->authorizeLogisticsModule('support');

        return view('logistics.support.index');
    }

    public function notifications()
    {
        $this->authorizeLogisticsModule('notifications');

        return view('logistics.notifications.index');
    }
}
