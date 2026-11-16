<?php

namespace App\Services\Admin;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Logistics\PredictiveAlert;
use App\Models\Logistics\RiskCase;
use App\Models\Rider\Rider;
use App\Models\SupportTicket;
use App\Models\Vendor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UnifiedIntelligenceService
{
    public function range(?string $from = null, ?string $to = null): array
    {
        $to = $to ? Carbon::parse($to)->endOfDay() : now()->endOfDay();
        $from = $from ? Carbon::parse($from)->startOfDay() : $to->copy()->subDays(29)->startOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }

    public function previousRange(Carbon $from, Carbon $to): array
    {
        $days = (int) $from->diffInDays($to) + 1;
        $previousTo = $from->copy()->subSecond();
        $previousFrom = $previousTo->copy()->subDays($days - 1)->startOfDay();

        return [$previousFrom, $previousTo];
    }

    public function overview(Carbon $from, Carbon $to, array $filters = []): array
    {
        $marketplace = $this->marketplace($from, $to, $filters);
        $logistics = $this->logistics($from, $to, $filters);
        $vendors = $this->vendors($from, $to, $filters);
        $products = $this->products($from, $to, $filters);
        $customers = $this->customers($from, $to, $filters, false);
        $risks = $this->risks($from, $to);
        $health = $this->health();

        return [
            'generated_at' => now(),
            'range' => [$from, $to],
            'marketplace' => $marketplace,
            'logistics' => $logistics,
            'vendors' => $vendors,
            'products' => $products,
            'customers' => $customers,
            'risks' => $risks,
            'health' => $health,
            'trend' => $marketplace['trend'],
            'status_distribution' => $marketplace['status_distribution'],
            'top_regions' => $this->regionPerformance($from, $to, $filters)->take(6),
            'top_vendors' => $vendors['rankings']->take(5),
            'insights' => $this->insights($from, $to, $filters),
        ];
    }

    public function marketplace(Carbon $from, Carbon $to, array $filters = []): array
    {
        [$previousFrom, $previousTo] = $this->previousRange($from, $to);
        $orders = $this->orderQuery($from, $to, $filters);
        $status = (clone $orders)->select('status', DB::raw('COUNT(*) AS total'))->groupBy('status')->pluck('total', 'status')->map(fn ($v) => (int) $v);

        $total = (int) (clone $orders)->count();
        $previousTotal = (int) $this->orderQuery($previousFrom, $previousTo, $filters)->count();
        $canceled = (int) (($status['canceled'] ?? 0) + ($status['cancelled'] ?? 0));
        $completed = (int) (($status['completed'] ?? 0) + ($status['delivered'] ?? 0));
        $awaiting = (int) ($status['pending'] ?? 0);
        $preparing = (int) (($status['accepted'] ?? 0) + ($status['processing'] ?? 0) + ($status['packed'] ?? 0));
        $value = (float) (clone $orders)->whereNotIn('status', ['canceled', 'cancelled'])->avg('total_amount');

        $trend = (clone $orders)
            ->selectRaw('DATE(created_at) AS day, COUNT(*) AS orders')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('day')
            ->get()
            ->map(fn ($row) => ['day' => (string) $row->day, 'orders' => (int) $row->orders])
            ->values();

        $labels = Order::STATUS_LABELS;

        return [
            'orders' => $total,
            'previous_orders' => $previousTotal,
            'growth' => $this->percentage($total, $previousTotal),
            'new_orders' => (int) (clone $orders)->where('created_at', '>=', now()->subDay())->count(),
            'confirmed' => (int) collect($status)->except(['pending', 'canceled', 'cancelled'])->sum(),
            'awaiting_vendor' => $awaiting,
            'preparing' => $preparing,
            'completed' => $completed,
            'canceled' => $canceled,
            'average_order_value' => round((float) ($value ?? 0), 2),
            'cancellation_rate' => $this->percentage($canceled, $total),
            'status_distribution' => collect($status)->map(fn ($count, $key) => [
                'status' => $key,
                'label' => $labels[$key] ?? ucfirst($key),
                'count' => $count,
                'percentage' => $this->percentage($count, $total),
            ])->values(),
            'trend' => $trend,
            'category_performance' => $this->categoryPerformance($from, $to, $filters),
            'product_demand' => $this->productDemand($from, $to, $filters),
            'region_demand' => $this->regionPerformance($from, $to, $filters),
            'vendor_performance' => $this->vendorPerformance($from, $to, $filters)->take(10),
        ];
    }

    public function logistics(Carbon $from, Carbon $to, array $filters = []): array
    {
        $query = $this->orderQuery($from, $to, $filters)
            ->where(function ($q) {
                $q->whereNotNull('logistics_tracking_id')->orWhereNotNull('logistics_status');
            });

        $status = (clone $query)->select('logistics_status', DB::raw('COUNT(*) AS total'))->groupBy('logistics_status')->pluck('total', 'logistics_status')->map(fn ($v) => (int) $v);
        $total = (int) (clone $query)->count();
        $delivered = (int) (($status['delivered'] ?? 0) + ($status['completed'] ?? 0));
        $failed = (int) (($status['failed'] ?? 0) + ($status['returned'] ?? 0));
        $pending = (int) (($status['pending'] ?? 0) + ($status['created'] ?? 0) + (clone $query)->whereNull('logistics_status')->count());
        $assigned = (int) (($status['assigned'] ?? 0) + ($status['accepted'] ?? 0));
        $inTransit = (int) (($status['picked_up'] ?? 0) + ($status['in_transit'] ?? 0));
        $delayed = (int) (clone $query)->whereNotIn('logistics_status', ['delivered', 'completed', 'failed', 'returned', 'cancelled'])->where(function ($q) {
            $q->whereDate('scheduled_for', '<', now()->toDateString())
                ->orWhere(fn ($w) => $w->whereNull('scheduled_for')->where('created_at', '<', now()->subDay()));
        })->count();

        $avgMinutes = (clone $query)->whereNotNull('pod_delivered_at')->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, pod_delivered_at)) AS avg_minutes')->value('avg_minutes');
        if ($avgMinutes === null) {
            $avgMinutes = (clone $query)->whereNotNull('delivered_at')->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, delivered_at)) AS avg_minutes')->value('avg_minutes');
        }

        $busyRiders = (clone $query)->whereIn('logistics_status', ['assigned', 'accepted', 'picked_up', 'in_transit'])->whereNotNull('assigned_driver_id')->distinct()->count('assigned_driver_id');
        $activeRiders = (int) Rider::where('is_active', true)->count();
        $onlineRiders = (int) Rider::where('is_active', true)->where('is_online', true)->count();

        $dispatch = collect();
        if (Schema::hasTable('logistics_dispatch_assignments')) {
            $dispatch = DB::table('logistics_dispatch_assignments')
                ->whereBetween('created_at', [$from, $to])
                ->select('status', DB::raw('COUNT(*) AS total'))
                ->groupBy('status')
                ->pluck('total', 'status');
        }

        return [
            'total' => $total,
            'pending_dispatch' => $pending,
            'assigned' => $assigned,
            'in_transit' => $inTransit,
            'completed' => $delivered,
            'failed' => $failed,
            'delayed' => $delayed,
            'success_rate' => $this->percentage($delivered, $delivered + $failed),
            'average_duration_minutes' => $avgMinutes === null ? null : (int) round((float) $avgMinutes),
            'active_riders' => $activeRiders,
            'online_riders' => $onlineRiders,
            'busy_riders' => $busyRiders,
            'rider_utilization' => $this->percentage($busyRiders, $activeRiders),
            'dispatch_acceptance_rate' => $this->percentage((int) ($dispatch['accepted'] ?? 0), (int) ($dispatch['accepted'] ?? 0) + (int) ($dispatch['rejected'] ?? 0) + (int) ($dispatch['timed_out'] ?? 0)),
            'status_distribution' => collect(Order::LOGISTICS_STATUS_LABELS)->map(fn ($label, $key) => [
                'status' => $key,
                'label' => $label,
                'count' => (int) ($status[$key] ?? 0),
            ])->values(),
            'location_performance' => $this->locationPerformance($from, $to, $filters),
            'rider_performance' => $this->riderPerformance($from, $to, $filters),
        ];
    }

    public function vendors(Carbon $from, Carbon $to, array $filters = []): array
    {
        $all = Vendor::query();
        $rankings = $this->vendorPerformance($from, $to, $filters);
        $inactive = Vendor::query()
            ->where('status', 'active')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('orders')->whereColumn('orders.vendor_id', 'vendors.id')->where('orders.created_at', '>=', now()->subDays(30)))
            ->count();

        return [
            'total' => (int) (clone $all)->count(),
            'approved' => (int) (clone $all)->where('is_approved', true)->count(),
            'pending' => (int) (clone $all)->where(fn ($q) => $q->where('is_approved', false)->orWhere('status', 'pending'))->count(),
            'active' => (int) (clone $all)->where('status', 'active')->count(),
            'suspended' => (int) (clone $all)->where(fn ($q) => $q->where('status', 'suspended')->orWhereNotNull('suspended_at'))->count(),
            'inactive_30d' => $inactive,
            'rankings' => $rankings,
            'needs_attention' => $rankings->filter(fn ($row) => $row['total_orders'] > 0 && ($row['cancellation_rate'] >= 15 || $row['completion_rate'] < 60 || $row['average_preparation_minutes'] > 1440))->take(10)->values(),
        ];
    }

    public function products(Carbon $from, Carbon $to, array $filters = []): array
    {
        $query = Product::query();
        if (! empty($filters['vendor_id'])) {
            $query->where('vendor_id', $filters['vendor_id']);
        }
        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        $lowThreshold = 5;
        $demand = $this->productDemand($from, $to, $filters);
        [$previousFrom, $previousTo] = $this->previousRange($from, $to);
        $previous = $this->productDemand($previousFrom, $previousTo, $filters)->keyBy('product_id');
        $declining = $demand->map(function ($row) use ($previous) {
            $before = (int) optional($previous->get($row['product_id']))['quantity'];
            $row['previous_quantity'] = $before;
            $row['change'] = $this->percentage($row['quantity'], $before);

            return $row;
        })->filter(fn ($row) => $row['previous_quantity'] > 0 && $row['change'] < -20)->sortBy('change')->take(10)->values();

        $outOfStock = (clone $query)->whereDoesntHave('variants', fn ($v) => $v->where('stock', '>', 0))->count();
        $lowStock = (clone $query)->whereHas('variants', fn ($v) => $v->where('stock', '>', 0)->where('stock', '<=', $lowThreshold))->count();
        $exceptions = (clone $query)->withSum('variants as stock_total', 'stock')
            ->where(fn ($q) => $q->whereDoesntHave('variants', fn ($v) => $v->where('stock', '>', 0))
                ->orWhereHas('variants', fn ($v) => $v->where('stock', '>', 0)->where('stock', '<=', $lowThreshold)))
            ->limit(15)
            ->get(['id', 'vendor_id', 'category_id', 'status', 'approval_status'])
            ->map(fn ($product) => [
                'id' => $product->id,
                'name' => $this->productName($product),
                'stock' => (int) ($product->stock_total ?? 0),
                'status' => $product->status ? 'active' : 'inactive',
                'approval_status' => $product->approval_status,
                'link' => route('admin.products.edit', $product->id),
            ])
            ->sortBy('stock')
            ->values();

        return [
            'total' => (int) (clone $query)->count(),
            'active' => (int) (clone $query)->where('status', true)->count(),
            'pending_approval' => (int) (clone $query)->where('approval_status', 'pending')->count(),
            'out_of_stock' => (int) $outOfStock,
            'low_stock' => (int) $lowStock,
            'low_stock_threshold' => $lowThreshold,
            'best_performing' => $demand->take(10),
            'declining' => $declining,
            'exceptions' => $exceptions,
        ];
    }

    public function customers(Carbon $from, Carbon $to, array $filters = [], bool $canViewIdentity = false): array
    {
        [$previousFrom, $previousTo] = $this->previousRange($from, $to);
        $orders = $this->orderQuery($from, $to, $filters);
        $previousOrders = $this->orderQuery($previousFrom, $previousTo, $filters);

        $active = (clone $orders)->whereNotNull('customer_id')->distinct()->count('customer_id');
        $previousActive = (clone $previousOrders)->whereNotNull('customer_id')->distinct()->count('customer_id');
        $repeat = (clone $orders)->whereNotNull('customer_id')->select('customer_id')->groupBy('customer_id')->havingRaw('COUNT(*) > 1')->get()->count();
        $new = Customer::whereBetween('created_at', [$from, $to])->count();
        $top = (clone $orders)->whereNotNull('customer_id')
            ->select('customer_id', DB::raw('COUNT(*) AS orders'), DB::raw('MAX(created_at) AS last_order_at'))
            ->groupBy('customer_id')
            ->orderByDesc('orders')
            ->limit(15)
            ->get();

        $customers = Customer::whereIn('id', $top->pluck('customer_id'))->get()->keyBy('id');

        return [
            'active' => $active,
            'previous_active' => $previousActive,
            'growth' => $this->percentage($active, $previousActive),
            'new' => $new,
            'repeat' => $repeat,
            'repeat_rate' => $this->percentage($repeat, $active),
            'complaints' => SupportTicket::whereBetween('created_at', [$from, $to])->whereIn('category', ['complaint', 'dispute', 'delivery', 'order'])->count(),
            'open_tickets' => SupportTicket::whereNotIn('status', ['resolved', 'closed'])->count(),
            'open_disputes' => SupportTicket::whereNotNull('dispute_type')->whereNotIn('status', ['resolved', 'closed'])->count(),
            'top_customers' => $top->map(function ($row) use ($customers, $canViewIdentity) {
                $customer = $customers->get($row->customer_id);

                return [
                    'id' => $row->customer_id,
                    'name' => $canViewIdentity ? ($customer?->name ?? 'Customer #'.$row->customer_id) : 'Customer #'.$row->customer_id,
                    'email' => $canViewIdentity ? $customer?->email : null,
                    'orders' => (int) $row->orders,
                    'last_order_at' => $row->last_order_at,
                    'link' => $canViewIdentity ? route('admin.customers.edit', $row->customer_id) : null,
                ];
            }),
            'geography' => $this->regionPerformance($from, $to, $filters)->take(10),
        ];
    }

    public function geography(Carbon $from, Carbon $to, array $filters = []): array
    {
        return [
            'regions' => $this->regionPerformance($from, $to, $filters),
            'locations' => $this->locationPerformance($from, $to, $filters),
            'rider_distribution' => Rider::where('is_active', true)->select('state', DB::raw('COUNT(*) AS riders'), DB::raw('SUM(CASE WHEN is_online = 1 THEN 1 ELSE 0 END) AS online'))->groupBy('state')->orderByDesc('riders')->get(),
            'vendor_distribution' => Vendor::where('status', 'active')->select('address', DB::raw('COUNT(*) AS vendors'))->whereNotNull('address')->groupBy('address')->orderByDesc('vendors')->limit(15)->get(),
        ];
    }

    public function risks(Carbon $from, Carbon $to): array
    {
        $riskCases = Schema::hasTable('logistics_risk_cases') ? RiskCase::query() : null;
        $alerts = Schema::hasTable('logistics_predictive_alerts') ? PredictiveAlert::query() : null;
        $integration = Schema::hasTable('integration_events') ? DB::table('integration_events') : null;

        $failedRegions = $this->locationPerformance($from, $to)->filter(fn ($row) => $row['total'] >= 3 && $row['failure_rate'] >= 25)->take(5);
        $canceledVendors = $this->vendorPerformance($from, $to)->filter(fn ($row) => $row['total_orders'] >= 3 && $row['cancellation_rate'] >= 20)->take(5);
        $currentTickets = SupportTicket::whereBetween('created_at', [$from, $to])->count();
        [$previousFrom, $previousTo] = $this->previousRange($from, $to);
        $previousTickets = SupportTicket::whereBetween('created_at', [$previousFrom, $previousTo])->count();

        $items = collect();

        foreach ($failedRegions as $region) {
            $items->push([
                'type' => 'delivery_failures',
                'severity' => $region['failure_rate'] >= 50 ? 'critical' : 'warning',
                'title' => 'Elevated delivery failures in '.$region['name'],
                'where' => $region['name'],
                'evidence' => $region['failed'].' failed/returned of '.$region['total'].' deliveries ('.$region['failure_rate'].'%).',
                'confidence' => 'Confirmed from delivery records',
                'action' => 'Review delivery records and rider coverage for this area.',
                'link' => route('admin.command-center.logistics', ['region_id' => $region['region_id']]),
            ]);
        }

        foreach ($canceledVendors as $vendor) {
            $items->push([
                'type' => 'vendor_cancellations',
                'severity' => $vendor['cancellation_rate'] >= 40 ? 'critical' : 'warning',
                'title' => 'Vendor cancellation rate requires review',
                'where' => $vendor['name'],
                'evidence' => $vendor['canceled'].' canceled of '.$vendor['total_orders'].' orders ('.$vendor['cancellation_rate'].'%).',
                'confidence' => 'Confirmed from order statuses',
                'action' => 'Review vendor operations and contact the vendor if the pattern continues.',
                'link' => route('admin.command-center.vendors', ['vendor_id' => $vendor['vendor_id']]),
            ]);
        }

        if ($previousTickets > 0) {
            $spike = $this->percentage($currentTickets, $previousTickets);
            if ($spike >= 30) {
                $items->push([
                    'type' => 'support_spike',
                    'severity' => $spike >= 75 ? 'critical' : 'warning',
                    'title' => 'Support ticket volume increased',
                    'where' => 'Customer support',
                    'evidence' => $currentTickets.' tickets this period versus '.$previousTickets.' previously ('.number_format($spike, 1).'% change).',
                    'confidence' => 'Confirmed from ticket records',
                    'action' => 'Review ticket categories and staffing coverage.',
                    'link' => route('admin.support-tickets.index'),
                ]);
            }
        }

        if ($integration) {
            $failures = (int) (clone $integration)->whereIn('status', ['failed', 'dead_letter'])->count();
            if ($failures > 0) {
                $items->push([
                    'type' => 'integration_failure',
                    'severity' => $failures >= 5 ? 'critical' : 'warning',
                    'title' => 'Integration events require attention',
                    'where' => 'System integration',
                    'evidence' => $failures.' failed or dead-letter integration events.',
                    'confidence' => 'Confirmed from integration events',
                    'action' => 'Inspect failed events and retry where safe.',
                    'link' => route('admin.command-center.health'),
                ]);
            }
        }

        return [
            'open_cases' => $riskCases ? (int) (clone $riskCases)->whereNotIn('status', ['resolved', 'closed'])->count() : null,
            'critical_cases' => $riskCases ? (int) (clone $riskCases)->whereNotIn('status', ['resolved', 'closed'])->where('severity', 'critical')->count() : null,
            'open_alerts' => $alerts ? (int) (clone $alerts)->whereNotIn('status', ['resolved'])->count() : null,
            'items' => $items->values(),
        ];
    }

    public function health(): array
    {
        $database = ['status' => 'healthy', 'detail' => 'Database connection succeeded.'];
        try {
            DB::select('SELECT 1');
        } catch (\Throwable $e) {
            $database = ['status' => 'critical', 'detail' => 'Database check failed.'];
        }

        $integrations = ['total' => 0, 'failed' => 0, 'pending' => 0, 'last_success' => null];
        if (Schema::hasTable('integration_events')) {
            $integrations['total'] = (int) DB::table('integration_events')->count();
            $integrations['failed'] = (int) DB::table('integration_events')->whereIn('status', ['failed', 'dead_letter'])->count();
            $integrations['pending'] = (int) DB::table('integration_events')->whereIn('status', ['pending', 'retrying'])->count();
            $integrations['last_success'] = DB::table('integration_events')->where('status', 'processed')->max('processed_at');
        }

        $jobs = ['pending' => 0, 'failed' => 0];
        if (Schema::hasTable('jobs')) {
            $jobs['pending'] = (int) DB::table('jobs')->count();
        }
        if (Schema::hasTable('failed_jobs')) {
            $jobs['failed'] = (int) DB::table('failed_jobs')->count();
        }

        $notifications = ['failed' => 0, 'pending' => 0];
        if (Schema::hasTable('notification_deliveries')) {
            $notifications['failed'] = (int) DB::table('notification_deliveries')->where('status', 'failed')->count();
            $notifications['pending'] = (int) DB::table('notification_deliveries')->whereIn('status', ['pending', 'queued'])->count();
        }

        $status = 'healthy';
        if ($database['status'] !== 'healthy' || $integrations['failed'] > 0 || $jobs['failed'] > 0 || $notifications['failed'] > 0) {
            $status = $integrations['failed'] >= 5 || $database['status'] !== 'healthy' ? 'critical' : 'degraded';
        }

        return [
            'status' => $status,
            'checked_at' => now(),
            'database' => $database,
            'integrations' => $integrations,
            'jobs' => $jobs,
            'notifications' => $notifications,
            'latest_order_at' => Order::max('created_at'),
            'latest_delivery_event_at' => Schema::hasTable('delivery_events') ? DB::table('delivery_events')->max('created_at') : null,
        ];
    }

    public function insights(Carbon $from, Carbon $to, array $filters = []): Collection
    {
        $items = collect();
        $marketplace = $this->marketplace($from, $to, $filters);
        $logistics = $this->logistics($from, $to, $filters);
        $vendors = $this->vendors($from, $to, $filters);

        if ($marketplace['growth'] <= -20 && $marketplace['previous_orders'] >= 5) {
            $items->push([
                'severity' => 'warning',
                'title' => 'Order volume declined',
                'evidence' => $marketplace['orders'].' orders versus '.$marketplace['previous_orders'].' in the previous period ('.number_format($marketplace['growth'], 1).'%).',
                'action' => 'Compare regional demand and vendor availability.',
                'link' => route('admin.command-center.marketplace'),
            ]);
        } elseif ($marketplace['growth'] >= 20 && $marketplace['previous_orders'] >= 5) {
            $items->push([
                'severity' => 'info',
                'title' => 'Order volume increased',
                'evidence' => $marketplace['orders'].' orders versus '.$marketplace['previous_orders'].' previously ('.number_format($marketplace['growth'], 1).'%).',
                'action' => 'Check vendor and rider capacity for the next period.',
                'link' => route('admin.command-center.marketplace'),
            ]);
        }

        $topRegion = $marketplace['region_demand']->first();
        if ($topRegion && $topRegion['orders'] > 0) {
            $items->push([
                'severity' => 'info',
                'title' => 'Highest order demand by area',
                'evidence' => $topRegion['name'].' recorded '.$topRegion['orders'].' orders in this period.',
                'action' => 'Review capacity and delivery coverage in this area.',
                'link' => route('admin.command-center.geography', ['region_id' => $topRegion['region_id']]),
            ]);
        }

        if ($logistics['delayed'] > 0) {
            $items->push([
                'severity' => $logistics['delayed'] >= 10 ? 'warning' : 'info',
                'title' => 'Deliveries are past their expected operating window',
                'evidence' => $logistics['delayed'].' active deliveries are past the scheduled date or older than 24 hours.',
                'action' => 'Review dispatch queues and rider workload.',
                'link' => route('admin.command-center.logistics'),
            ]);
        }

        foreach ($vendors['needs_attention']->take(3) as $vendor) {
            $items->push([
                'severity' => 'warning',
                'title' => 'Vendor performance needs review',
                'evidence' => $vendor['name'].' has a '.$vendor['cancellation_rate'].'% cancellation rate and '.$vendor['completion_rate'].'% completion rate.',
                'action' => 'Open vendor intelligence for supporting orders.',
                'link' => route('admin.command-center.vendors', ['vendor_id' => $vendor['vendor_id']]),
            ]);
        }

        return $items->values();
    }

    public function reportData(string $type, Carbon $from, Carbon $to, array $filters = [], bool $canViewIdentity = false): array
    {
        return match ($type) {
            'marketplace' => $this->reportMarketplace($from, $to, $filters),
            'vendors' => $this->reportVendors($from, $to, $filters),
            'products' => $this->reportProducts($from, $to, $filters),
            'customers' => $this->reportCustomers($from, $to, $filters, $canViewIdentity),
            'inventory' => $this->reportInventory($from, $to, $filters),
            'logistics' => $this->reportLogistics($from, $to, $filters),
            'riders' => $this->reportRiders($from, $to, $filters),
            'geography' => $this->reportGeography($from, $to, $filters),
            'support' => $this->reportSupport($from, $to),
            'risks' => $this->reportRisks($from, $to),
            'health' => $this->reportHealth(),
            default => $this->reportMarketplace($from, $to, $filters),
        };
    }

    public function assistantAnswer(string $question, Carbon $from, Carbon $to, bool $canViewIdentity = false): array
    {
        $q = strtolower($question);

        if (str_contains($q, 'reject') || str_contains($q, 'cancel') || str_contains($q, 'vendor')) {
            $vendors = $this->vendorPerformance($from, $to)->filter(fn ($row) => $row['total_orders'] > 0)->sortByDesc('cancellation_rate')->take(5)->values();

            return $this->assistantResponse(
                'Vendor cancellation rates for the selected period.',
                $vendors->map(fn ($v) => $v['name'].': '.$v['cancellation_rate'].'% canceled ('.$v['canceled'].' of '.$v['total_orders'].' orders), '.$v['completion_rate'].'% completed.')->all(),
                'Derived from order statuses for orders created in the selected date range. Vendor rejection is not stored as a separate status; cancellation is the available rejection proxy.',
                route('admin.command-center.vendors')
            );
        }

        if ((str_contains($q, 'location') || str_contains($q, 'region') || str_contains($q, 'area')) && (str_contains($q, 'fail') || str_contains($q, 'delay'))) {
            $locations = $this->locationPerformance($from, $to)->filter(fn ($row) => $row['total'] > 0)->sortByDesc('failure_rate')->take(5)->values();

            return $this->assistantResponse(
                'Locations ranked by delivery failure rate.',
                $locations->map(fn ($l) => $l['name'].': '.$l['failure_rate'].'% failure rate ('.$l['failed'].' failed/returned of '.$l['total'].' deliveries).')->all(),
                'Derived from delivery statuses joined to order shipping addresses.',
                route('admin.command-center.geography')
            );
        }

        if (str_contains($q, 'delivery') && (str_contains($q, 'time') || str_contains($q, 'duration'))) {
            $logistics = $this->logistics($from, $to);
            $duration = $logistics['average_duration_minutes'];

            return $this->assistantResponse(
                'Delivery duration for the selected period.',
                [$duration === null ? 'No delivered records with timestamps were available.' : 'Average delivery duration: '.$duration.' minutes.', $logistics['delayed'].' active deliveries are delayed.'],
                'Derived from delivered order timestamps. Missing timestamps are excluded rather than estimated.',
                route('admin.command-center.logistics')
            );
        }

        if (str_contains($q, 'out of stock') || str_contains($q, 'stock') || str_contains($q, 'inventory')) {
            $products = $this->products($from, $to)['exceptions'];

            return $this->assistantResponse(
                'Products with the lowest stock levels.',
                $products->take(8)->map(fn ($p) => $p['name'].': '.$p['stock'].' units ('.$p['status'].', approval '.$p['approval_status'].').')->all(),
                'Derived from current product stock values. Low-stock uses the platform threshold of 5 units because no per-product threshold is consistently configured.',
                route('admin.command-center.marketplace')
            );
        }

        if (str_contains($q, 'zone') || str_contains($q, 'rider') || str_contains($q, 'capacity')) {
            $logistics = $this->logistics($from, $to);
            $zones = $this->zoneCapacity();

            return $this->assistantResponse(
                'Rider capacity and busy rider utilisation.',
                array_merge([
                    $logistics['active_riders'].' active riders, '.$logistics['online_riders'].' online, '.$logistics['busy_riders'].' busy ('.$logistics['rider_utilization'].'% utilisation).',
                ], $zones->map(fn ($z) => $z['name'].': '.$z['riders'].' riders, '.$z['open_deliveries'].' active deliveries.')->all()),
                'Derived from rider availability and active delivery assignments. Zone demand uses delivery zones linked to order locations.',
                route('admin.command-center.logistics')
            );
        }

        if (str_contains($q, 'sync') || str_contains($q, 'integration')) {
            $health = $this->health();

            return $this->assistantResponse(
                'Integration health for the selected period.',
                ['Status: '.$health['status'].'.', $health['integrations']['failed'].' failed/dead-letter events, '.$health['integrations']['pending'].' pending/retrying events.', 'Failed jobs: '.$health['jobs']['failed'].'.'],
                'Derived from integration event and queue records. The dashboard loading is not treated as a health signal.',
                route('admin.command-center.health')
            );
        }

        if (str_contains($q, 'growth') || str_contains($q, 'region')) {
            $regions = $this->regionPerformance($from, $to)->take(8);

            return $this->assistantResponse(
                'Order demand by region.',
                $regions->map(fn ($r) => $r['name'].': '.$r['orders'].' orders, '.$r['delivered'].' delivered, '.$r['failed'].' failed/returned.')->all(),
                'Derived from orders joined to shipping address regions. Customers without a region are grouped as Unassigned.',
                route('admin.command-center.geography')
            );
        }

        $marketplace = $this->marketplace($from, $to);

        return $this->assistantResponse(
            'Marketplace and logistics summary for the selected period.',
            [$marketplace['orders'].' orders ('.number_format($marketplace['growth'], 1).'% growth versus the previous period).', $marketplace['completed'].' completed and '.$marketplace['canceled'].' canceled orders.', 'Average order value: '.number_format($marketplace['average_order_value'], 2).'.'],
            'Derived from order, delivery and support records in the selected date range.',
            route('admin.command-center.marketplace')
        );
    }

    protected function assistantResponse(string $answer, array $bullets, string $method, string $link): array
    {
        return [
            'answer' => $answer,
            'bullets' => array_values(array_filter($bullets)),
            'method' => $method,
            'link' => $link,
        ];
    }

    protected function orderQuery(Carbon $from, Carbon $to, array $filters = [])
    {
        return Order::query()
            ->whereBetween('created_at', [$from, $to])
            ->when(! empty($filters['vendor_id']), fn ($q, $v) => $q->where('vendor_id', $v))
            ->when(! empty($filters['status']), fn ($q, $v) => $q->where('status', $v))
            ->when(! empty($filters['region_id']), fn ($q, $v) => $q->whereHas('shippingAddress', fn ($a) => $a->where('region_id', $v)))
            ->when(! empty($filters['location_id']), fn ($q, $v) => $q->whereHas('shippingAddress', fn ($a) => $a->where('location_id', $v)))
            ->when(! empty($filters['category_id']), fn ($q, $v) => $q->whereHas('details.product', fn ($p) => $p->where('category_id', $v)))
            ->when(! empty($filters['product_id']), fn ($q, $v) => $q->whereHas('details', fn ($d) => $d->where('product_id', $v)));
    }

    protected function categoryPerformance(Carbon $from, Carbon $to, array $filters = []): Collection
    {
        if (! Schema::hasTable('order_details')) {
            return collect();
        }

        $rows = DB::table('order_details')
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->join('products', 'products.id', '=', 'order_details.product_id')
            ->whereBetween('orders.created_at', [$from, $to])
            ->whereIn('orders.status', ['completed', 'delivered', 'processing', 'packed', 'accepted'])
            ->when(! empty($filters['vendor_id']), fn ($q, $v) => $q->where('orders.vendor_id', $v))
            ->when(! empty($filters['category_id']), fn ($q, $v) => $q->where('products.category_id', $v))
            ->select('products.category_id', DB::raw('SUM(order_details.quantity) AS quantity'), DB::raw('COUNT(DISTINCT orders.id) AS orders'))
            ->groupBy('products.category_id')
            ->orderByDesc('quantity')
            ->get();

        $names = Category::with('translations')->whereIn('id', $rows->pluck('category_id'))->get()->keyBy('id');

        return $rows->map(fn ($row) => [
            'category_id' => $row->category_id,
            'name' => $names->get($row->category_id)?->displayName() ?? 'Category #'.$row->category_id,
            'quantity' => (int) $row->quantity,
            'orders' => (int) $row->orders,
        ])->values();
    }

    protected function productDemand(Carbon $from, Carbon $to, array $filters = []): Collection
    {
        if (! Schema::hasTable('order_details')) {
            return collect();
        }

        $rows = DB::table('order_details')
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->join('products', 'products.id', '=', 'order_details.product_id')
            ->whereBetween('orders.created_at', [$from, $to])
            ->whereIn('orders.status', ['completed', 'delivered', 'processing', 'packed', 'accepted'])
            ->when(! empty($filters['vendor_id']), fn ($q, $v) => $q->where('orders.vendor_id', $v))
            ->when(! empty($filters['category_id']), fn ($q, $v) => $q->where('products.category_id', $v))
            ->select('products.id', 'products.vendor_id', DB::raw('SUM(order_details.quantity) AS quantity'), DB::raw('COUNT(DISTINCT orders.id) AS orders'))
            ->groupBy('products.id', 'products.vendor_id')
            ->orderByDesc('quantity')
            ->limit(50)
            ->get();

        $products = Product::with('translations')->whereIn('id', $rows->pluck('id'))->get()->keyBy('id');
        $stocks = DB::table('product_variants')->whereIn('product_id', $rows->pluck('id'))->select('product_id', DB::raw('SUM(stock) AS stock'))->groupBy('product_id')->pluck('stock', 'product_id');

        return $rows->map(fn ($row) => [
            'product_id' => $row->id,
            'name' => $this->productName($products->get($row->id)),
            'quantity' => (int) $row->quantity,
            'orders' => (int) $row->orders,
            'stock' => (int) ($stocks->get($row->id) ?? 0),
        ])->values();
    }

    protected function vendorPerformance(Carbon $from, Carbon $to, array $filters = []): Collection
    {
        $completed = ['completed', 'delivered'];
        $canceled = ['canceled', 'cancelled'];
        $accepted = ['accepted', 'processing', 'packed', 'ready_for_pickup', 'dispatched', 'rider_arrived', 'delivered', 'completed', 'returned'];

        $rows = Order::query()
            ->whereBetween('created_at', [$from, $to])
            ->when(! empty($filters['vendor_id']), fn ($q, $v) => $q->where('vendor_id', $v))
            ->when(! empty($filters['region_id']), fn ($q, $v) => $q->whereHas('shippingAddress', fn ($a) => $a->where('region_id', $v)))
            ->when(! empty($filters['location_id']), fn ($q, $v) => $q->whereHas('shippingAddress', fn ($a) => $a->where('location_id', $v)))
            ->selectRaw('vendor_id, COUNT(*) AS total_orders')
            ->selectRaw("SUM(CASE WHEN status IN ('".implode("','", $completed)."') THEN 1 ELSE 0 END) AS completed_orders")
            ->selectRaw("SUM(CASE WHEN status IN ('".implode("','", $canceled)."') THEN 1 ELSE 0 END) AS canceled_orders")
            ->selectRaw("SUM(CASE WHEN status IN ('".implode("','", $accepted)."') THEN 1 ELSE 0 END) AS accepted_orders")
            ->whereNotNull('vendor_id')
            ->groupBy('vendor_id')
            ->orderByDesc('total_orders')
            ->get();

        $vendors = Vendor::whereIn('id', $rows->pluck('vendor_id'))->get()->keyBy('id');
        $prep = collect();
        if (Schema::hasTable('order_status_histories')) {
            $prep = DB::table('order_status_histories AS h')
                ->join('orders', 'orders.id', '=', 'h.order_id')
                ->where('h.to_status', 'packed')
                ->whereBetween('orders.created_at', [$from, $to])
                ->whereNotNull('orders.vendor_id')
                ->select('orders.vendor_id', DB::raw('AVG(TIMESTAMPDIFF(MINUTE, orders.created_at, h.created_at)) AS avg_minutes'))
                ->groupBy('orders.vendor_id')
                ->pluck('avg_minutes', 'orders.vendor_id');
        }

        return $rows->map(function ($row) use ($vendors, $prep) {
            $vendor = $vendors->get($row->vendor_id);
            $total = (int) $row->total_orders;
            $canceled = (int) $row->canceled_orders;
            $completed = (int) $row->completed_orders;
            $accepted = (int) $row->accepted_orders;
            $avg = $prep->get($row->vendor_id);

            return [
                'vendor_id' => $row->vendor_id,
                'name' => $vendor?->shop_name ?: ($vendor?->name ?? 'Vendor #'.$row->vendor_id),
                'status' => $vendor?->status,
                'verified' => $vendor?->verification_status,
                'total_orders' => $total,
                'accepted_orders' => $accepted,
                'completed_orders' => $completed,
                'canceled' => $canceled,
                'acceptance_rate' => $this->percentage($accepted, $total),
                'cancellation_rate' => $this->percentage($canceled, $total),
                'completion_rate' => $this->percentage($completed, $total),
                'average_preparation_minutes' => $avg === null ? null : (int) round((float) $avg),
                'link' => route('admin.command-center.vendors', ['vendor_id' => $row->vendor_id]),
            ];
        })->values();
    }

    protected function regionPerformance(Carbon $from, Carbon $to, array $filters = []): Collection
    {
        $rows = DB::table('shipping_addresses')
            ->join('orders', 'orders.id', '=', 'shipping_addresses.order_id')
            ->leftJoin('regions', 'regions.id', '=', 'shipping_addresses.region_id')
            ->whereBetween('orders.created_at', [$from, $to])
            ->when(! empty($filters['region_id']), fn ($q, $v) => $q->where('shipping_addresses.region_id', $v))
            ->when(! empty($filters['location_id']), fn ($q, $v) => $q->where('shipping_addresses.location_id', $v))
            ->when(! empty($filters['vendor_id']), fn ($q, $v) => $q->where('orders.vendor_id', $v))
            ->select('shipping_addresses.region_id', 'regions.name', DB::raw('COUNT(*) AS orders'))
            ->selectRaw('SUM(CASE WHEN orders.logistics_status = "delivered" THEN 1 ELSE 0 END) AS delivered')
            ->selectRaw('SUM(CASE WHEN orders.logistics_status IN ("failed", "returned") THEN 1 ELSE 0 END) AS failed')
            ->groupBy('shipping_addresses.region_id', 'regions.name')
            ->orderByDesc('orders')
            ->get();

        return $rows->map(fn ($row) => [
            'region_id' => $row->region_id,
            'name' => $row->name ?: 'Unassigned',
            'orders' => (int) $row->orders,
            'delivered' => (int) $row->delivered,
            'failed' => (int) $row->failed,
            'failure_rate' => $this->percentage((int) $row->failed, (int) $row->delivered + (int) $row->failed),
        ])->values();
    }

    protected function locationPerformance(Carbon $from, Carbon $to, array $filters = []): Collection
    {
        $rows = DB::table('shipping_addresses')
            ->join('orders', 'orders.id', '=', 'shipping_addresses.order_id')
            ->leftJoin('locations', 'locations.id', '=', 'shipping_addresses.location_id')
            ->leftJoin('regions', 'regions.id', '=', 'shipping_addresses.region_id')
            ->whereBetween('orders.created_at', [$from, $to])
            ->when(! empty($filters['region_id']), fn ($q, $v) => $q->where('shipping_addresses.region_id', $v))
            ->when(! empty($filters['location_id']), fn ($q, $v) => $q->where('shipping_addresses.location_id', $v))
            ->when(! empty($filters['vendor_id']), fn ($q, $v) => $q->where('orders.vendor_id', $v))
            ->select('shipping_addresses.region_id', 'shipping_addresses.location_id', 'locations.name AS location_name', 'regions.name AS region_name', DB::raw('COUNT(*) AS total'))
            ->selectRaw('SUM(CASE WHEN orders.logistics_status = "delivered" THEN 1 ELSE 0 END) AS delivered')
            ->selectRaw('SUM(CASE WHEN orders.logistics_status IN ("failed", "returned") THEN 1 ELSE 0 END) AS failed')
            ->groupBy('shipping_addresses.region_id', 'shipping_addresses.location_id', 'locations.name', 'regions.name')
            ->orderByDesc('total')
            ->get();

        return $rows->map(fn ($row) => [
            'region_id' => $row->region_id,
            'location_id' => $row->location_id,
            'name' => $row->location_name ?: ($row->region_name ?: 'Unassigned'),
            'region' => $row->region_name ?: 'Unassigned',
            'total' => (int) $row->total,
            'delivered' => (int) $row->delivered,
            'failed' => (int) $row->failed,
            'failure_rate' => $this->percentage((int) $row->failed, (int) $row->delivered + (int) $row->failed),
        ])->values();
    }

    protected function riderPerformance(Carbon $from, Carbon $to, array $filters = []): Collection
    {
        $rows = Order::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('assigned_driver_id')
            ->when(! empty($filters['region_id']), fn ($q, $v) => $q->whereHas('shippingAddress', fn ($a) => $a->where('region_id', $v)))
            ->select('assigned_driver_id', DB::raw('COUNT(*) AS deliveries'))
            ->selectRaw('SUM(CASE WHEN logistics_status = "delivered" THEN 1 ELSE 0 END) AS delivered')
            ->selectRaw('SUM(CASE WHEN logistics_status IN ("failed", "returned") THEN 1 ELSE 0 END) AS failed')
            ->groupBy('assigned_driver_id')
            ->orderByDesc('deliveries')
            ->get();

        $riders = Rider::whereIn('id', $rows->pluck('assigned_driver_id'))->get()->keyBy('id');

        return $rows->map(fn ($row) => [
            'rider_id' => $row->assigned_driver_id,
            'name' => $riders->get($row->assigned_driver_id)?->full_name ?? 'Rider #'.$row->assigned_driver_id,
            'deliveries' => (int) $row->deliveries,
            'delivered' => (int) $row->delivered,
            'failed' => (int) $row->failed,
            'success_rate' => $this->percentage((int) $row->delivered, (int) $row->delivered + (int) $row->failed),
        ])->values();
    }

    protected function zoneCapacity(): Collection
    {
        if (! Schema::hasTable('logistics_zones')) {
            return collect();
        }

        return DB::table('logistics_zones AS z')
            ->leftJoin('logistics_zone_riders AS zr', 'zr.zone_id', '=', 'z.id')
            ->leftJoin('logistics_zone_locations AS zl', 'zl.zone_id', '=', 'z.id')
            ->leftJoin('shipping_addresses AS sa', 'sa.location_id', '=', 'zl.location_id')
            ->leftJoin('orders', function ($join) {
                $join->on('orders.id', '=', 'sa.order_id')->whereIn('orders.logistics_status', ['pending', 'created', 'assigned', 'accepted', 'picked_up', 'in_transit']);
            })
            ->where('z.is_active', true)
            ->select('z.id', 'z.name', 'z.capacity', DB::raw('COUNT(DISTINCT zr.rider_id) AS riders'), DB::raw('COUNT(DISTINCT orders.id) AS open_deliveries'))
            ->groupBy('z.id', 'z.name', 'z.capacity')
            ->orderByDesc('open_deliveries')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'capacity' => $row->capacity,
                'riders' => (int) $row->riders,
                'open_deliveries' => (int) $row->open_deliveries,
            ]);
    }

    protected function productName(?Product $product): string
    {
        if (! $product) {
            return 'Unknown product';
        }

        return $product->getTranslation('name') ?: 'Product #'.$product->id;
    }

    protected function percentage(float|int $part, float|int $whole): float
    {
        if ((float) $whole <= 0) {
            return 0.0;
        }

        return round(((float) $part / (float) $whole) * 100, 1);
    }

    protected function reportMarketplace(Carbon $from, Carbon $to, array $filters): array
    {
        $data = $this->marketplace($from, $to, $filters);

        return [
            'title' => 'Marketplace performance',
            'columns' => ['Metric', 'Value'],
            'rows' => [
                ['Total orders', $data['orders']],
                ['Previous period orders', $data['previous_orders']],
                ['Order growth (%)', $data['growth']],
                ['Completed orders', $data['completed']],
                ['Canceled orders', $data['canceled']],
                ['Average order value', $data['average_order_value']],
                ['Cancellation rate (%)', $data['cancellation_rate']],
            ],
        ];
    }

    protected function reportVendors(Carbon $from, Carbon $to, array $filters): array
    {
        $rows = $this->vendorPerformance($from, $to, $filters);

        return [
            'title' => 'Vendor performance',
            'columns' => ['Vendor', 'Orders', 'Accepted', 'Completed', 'Canceled', 'Completion rate (%)', 'Cancellation rate (%)', 'Avg preparation (min)'],
            'rows' => $rows->map(fn ($r) => [$r['name'], $r['total_orders'], $r['accepted_orders'], $r['completed_orders'], $r['canceled'], $r['completion_rate'], $r['cancellation_rate'], $r['average_preparation_minutes']])->all(),
        ];
    }

    protected function reportProducts(Carbon $from, Carbon $to, array $filters): array
    {
        $rows = $this->productDemand($from, $to, $filters);

        return [
            'title' => 'Product demand',
            'columns' => ['Product', 'Quantity', 'Orders', 'Current stock'],
            'rows' => $rows->map(fn ($r) => [$r['name'], $r['quantity'], $r['orders'], $r['stock']])->all(),
        ];
    }

    protected function reportCustomers(Carbon $from, Carbon $to, array $filters, bool $canViewIdentity = false): array
    {
        $data = $this->customers($from, $to, $filters, $canViewIdentity);

        return [
            'title' => 'Customer activity',
            'columns' => ['Customer', 'Email', 'Orders', 'Last order'],
            'rows' => $data['top_customers']->map(fn ($r) => [$r['name'], $r['email'], $r['orders'], $r['last_order_at']])->all(),
        ];
    }

    protected function reportInventory(Carbon $from, Carbon $to, array $filters): array
    {
        $data = $this->products($from, $to, $filters);

        return [
            'title' => 'Inventory exceptions',
            'columns' => ['Product', 'Stock', 'Status', 'Approval'],
            'rows' => $data['exceptions']->map(fn ($r) => [$r['name'], $r['stock'], $r['status'], $r['approval_status']])->all(),
        ];
    }

    protected function reportLogistics(Carbon $from, Carbon $to, array $filters): array
    {
        $data = $this->logistics($from, $to, $filters);

        return [
            'title' => 'Logistics performance',
            'columns' => ['Metric', 'Value'],
            'rows' => [
                ['Total deliveries', $data['total']],
                ['Delivered', $data['completed']],
                ['Failed or returned', $data['failed']],
                ['Delayed', $data['delayed']],
                ['Success rate (%)', $data['success_rate']],
                ['Average delivery duration (min)', $data['average_duration_minutes']],
                ['Active riders', $data['active_riders']],
                ['Busy riders', $data['busy_riders']],
                ['Rider utilization (%)', $data['rider_utilization']],
            ],
        ];
    }

    protected function reportRiders(Carbon $from, Carbon $to, array $filters): array
    {
        $rows = $this->riderPerformance($from, $to, $filters);

        return [
            'title' => 'Rider performance',
            'columns' => ['Rider', 'Deliveries', 'Delivered', 'Failed/returned', 'Success rate (%)'],
            'rows' => $rows->map(fn ($r) => [$r['name'], $r['deliveries'], $r['delivered'], $r['failed'], $r['success_rate']])->all(),
        ];
    }

    protected function reportGeography(Carbon $from, Carbon $to, array $filters): array
    {
        $rows = $this->locationPerformance($from, $to, $filters);

        return [
            'title' => 'Geographical performance',
            'columns' => ['Area', 'Region', 'Orders', 'Delivered', 'Failed/returned', 'Failure rate (%)'],
            'rows' => $rows->map(fn ($r) => [$r['name'], $r['region'], $r['total'], $r['delivered'], $r['failed'], $r['failure_rate']])->all(),
        ];
    }

    protected function reportSupport(Carbon $from, Carbon $to): array
    {
        $rows = SupportTicket::whereBetween('created_at', [$from, $to])
            ->select('category', DB::raw('COUNT(*) AS total'), DB::raw("SUM(CASE WHEN status IN ('resolved', 'closed') THEN 1 ELSE 0 END) AS resolved"))
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        return [
            'title' => 'Customer support',
            'columns' => ['Category', 'Tickets', 'Resolved/closed'],
            'rows' => $rows->map(fn ($r) => [$r->category ?: 'Uncategorized', (int) $r->total, (int) $r->resolved])->all(),
        ];
    }

    protected function reportRisks(Carbon $from, Carbon $to): array
    {
        $items = $this->risks($from, $to)['items'];

        return [
            'title' => 'Operational risks',
            'columns' => ['Severity', 'Type', 'Finding', 'Evidence', 'Confidence'],
            'rows' => $items->map(fn ($r) => [$r['severity'], $r['type'], $r['title'], $r['evidence'], $r['confidence']])->all(),
        ];
    }

    protected function reportHealth(): array
    {
        $health = $this->health();

        return [
            'title' => 'System health',
            'columns' => ['Signal', 'Value'],
            'rows' => [
                ['Overall status', $health['status']],
                ['Database', $health['database']['detail']],
                ['Failed integration events', $health['integrations']['failed']],
                ['Pending integration events', $health['integrations']['pending']],
                ['Pending jobs', $health['jobs']['pending']],
                ['Failed jobs', $health['jobs']['failed']],
                ['Failed notifications', $health['notifications']['failed']],
                ['Last successful integration', $health['integrations']['last_success']],
            ],
        ];
    }
}
