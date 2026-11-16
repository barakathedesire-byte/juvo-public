<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\SalesAttribution;
use App\Models\SalesLead;
use App\Models\SalesRep;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PerformanceController extends Controller
{
    protected function agent(): SalesRep
    {
        return request()->attributes->get('agent');
    }

    protected function dateRange(Request $request): array
    {
        $preset = $request->query('range', 'month');

        return match ($preset) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            'week' => [now()->startOfWeek(), now()->endOfWeek()],
            'last_month' => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
            'quarter' => [now()->startOfQuarter(), now()->endOfQuarter()],
            'year' => [now()->startOfYear(), now()->endOfYear()],
            'custom' => [
                $request->query('from') ? \Carbon\Carbon::parse($request->query('from'))->startOfDay() : now()->startOfMonth(),
                $request->query('to') ? \Carbon\Carbon::parse($request->query('to'))->endOfDay() : now()->endOfMonth(),
            ],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };
    }

    protected function attributedOrders(SalesRep $agent, $start, $end)
    {
        $ids = SalesAttribution::where('sales_rep_id', $agent->id)->pluck('order_id');

        return Order::whereIn('id', $ids)
            ->whereBetween('created_at', [$start, $end])
            ->with(['customer', 'details.product.category']);
    }

    public function index(Request $request)
    {
        $agent = $this->agent();
        [$start, $end] = $this->dateRange($request);
        $length = $start->diffInDays($end) + 1;
        $prevStart = $start->copy()->subDays($length);
        $prevEnd = $start->copy()->subDay();

        $orders = $this->attributedOrders($agent, $start, $end)->get();
        $prevOrders = $this->attributedOrders($agent, $prevStart, $prevEnd)->get();

        $completedStatuses = ['delivered', 'completed'];
        $cancelledStatuses = ['canceled', 'cancelled'];
        $returnedStatuses = ['returned'];

        $gross = (float) $orders->sum('total_amount');
        $completed = $orders->whereIn('status', $completedStatuses);
        $completedValue = (float) $completed->sum('total_amount');
        $cancelled = $orders->whereIn('status', $cancelledStatuses)->count();
        $returned = $orders->whereIn('status', $returnedStatuses)->count();
        $deliveredCount = $completed->count();
        $orderCount = $orders->count();

        $customersAcquired = Customer::where('sales_rep_id', $agent->id)
            ->whereBetween('created_at', [$start, $end])->count();

        $leadsGenerated = SalesLead::where('sales_rep_id', $agent->id)
            ->whereBetween('created_at', [$start, $end])->count();

        $leadsConverted = SalesLead::where('sales_rep_id', $agent->id)
            ->where('status', 'converted')
            ->whereBetween('converted_at', [$start, $end])->count();

        $repeatCustomers = Customer::where('sales_rep_id', $agent->id)
            ->whereHas('orders', fn ($q) => $q->whereIn('id', SalesAttribution::where('sales_rep_id', $agent->id)->pluck('order_id')))
            ->get()
            ->filter(fn ($customer) => $customer->orders()->whereIn('id', SalesAttribution::where('sales_rep_id', $agent->id)->pluck('order_id'))->whereIn('status', $completedStatuses)->count() >= 2)
            ->count();

        $avgOrderValue = $orderCount > 0 ? $gross / $orderCount : 0;

        $prevGross = (float) $prevOrders->sum('total_amount');
        $growth = $prevGross > 0 ? round((($gross - $prevGross) / $prevGross) * 100, 1) : 0;

        $conversionRate = $leadsGenerated > 0 ? round(($leadsConverted / $leadsGenerated) * 100, 1) : 0;

        $target = (float) ($agent->monthly_target ?? 0);
        $targetProgress = $target > 0 ? round(($completedValue / $target) * 100, 1) : 0;

        $attributions = SalesAttribution::where('sales_rep_id', $agent->id)
            ->whereIn('order_id', $orders->pluck('id'))
            ->get();

        $commissionEarned = (float) $attributions->sum('commission_amount');
        $commissionPending = (float) $attributions->where('status', 'pending')->sum('commission_amount');
        $commissionEligible = (float) $attributions->where('status', 'approved')->sum('commission_amount');

        // Sales trends
        $daily = $orders->groupBy(fn ($order) => $order->created_at->format('Y-m-d'))
            ->map(fn ($group) => ['orders' => $group->count(), 'value' => (float) $group->sum('total_amount')]);

        // Funnel
        $leads = SalesLead::where('sales_rep_id', $agent->id)->whereBetween('created_at', [$start, $end])->get();
        $funnel = [
            'leads' => $leads->count(),
            'contacted' => $leads->whereIn('status', ['contacted', 'interested', 'follow_up', 'converted'])->count(),
            'interested' => $leads->whereIn('status', ['interested', 'follow_up', 'converted'])->count(),
            'converted' => $leads->where('status', 'converted')->count(),
            'ordering' => $orders->pluck('customer_id')->unique()->count(),
            'delivered' => $completed->pluck('customer_id')->unique()->count(),
        ];

        // Customer performance
        $topCustomers = Customer::where('sales_rep_id', $agent->id)
            ->withCount(['orders' => fn ($q) => $q->whereIn('id', SalesAttribution::where('sales_rep_id', $agent->id)->pluck('order_id'))])
            ->withSum(['orders' => fn ($q) => $q->whereIn('id', SalesAttribution::where('sales_rep_id', $agent->id)->pluck('order_id'))], 'total_amount')
            ->orderByDesc('orders_sum_total_amount')
            ->take(10)
            ->get();

        // Product/category performance
        $productPerformance = [];
        $categoryPerformance = [];
        foreach ($orders as $order) {
            foreach ($order->details as $detail) {
                $product = $detail->product;
                if (! $product) continue;
                $key = $product->id;
                if (! isset($productPerformance[$key])) {
                    $productPerformance[$key] = ['name' => $product->translation?->name ?: 'Product #'.$product->id, 'qty' => 0, 'value' => 0];
                }
                $productPerformance[$key]['qty'] += $detail->quantity;
                $productPerformance[$key]['value'] += (float) $detail->price * $detail->quantity;

                $cat = $product->category?->displayName() ?: 'Uncategorized';
                if (! isset($categoryPerformance[$cat])) {
                    $categoryPerformance[$cat] = ['qty' => 0, 'value' => 0, 'orders' => 0];
                }
                $categoryPerformance[$cat]['qty'] += $detail->quantity;
                $categoryPerformance[$cat]['value'] += (float) $detail->price * $detail->quantity;
                $categoryPerformance[$cat]['orders']++;
            }
        }
        arsort($productPerformance);
        arsort($categoryPerformance);

        // Activity metrics
        $activity = [
            'leads_added' => $leadsGenerated,
            'leads_contacted' => $leads->whereIn('status', ['contacted', 'interested', 'follow_up', 'converted'])->count(),
            'followups_completed' => \App\Models\LeadFollowUp::where('sales_rep_id', $agent->id)->whereNotNull('completed_at')->whereBetween('completed_at', [$start, $end])->count(),
            'followups_overdue' => \App\Models\LeadFollowUp::where('sales_rep_id', $agent->id)->whereNull('completed_at')->where('scheduled_at', '<', now())->count(),
            'customers_registered' => $customersAcquired,
            'orders_placed' => $orderCount,
            'orders_delivered' => $deliveredCount,
            'interactions' => \App\Models\CustomerNote::where('sales_rep_id', $agent->id)->whereBetween('created_at', [$start, $end])->count()
                + \App\Models\LeadNote::where('sales_rep_id', $agent->id)->whereBetween('created_at', [$start, $end])->count(),
        ];

        // Insights
        $insights = [];
        if ($growth > 0) $insights[] = 'Sales increased by '.$growth.'% compared with the previous period.';
        if ($growth < 0) $insights[] = 'Sales decreased by '.abs($growth).'% compared with the previous period.';
        if ($conversionRate > 0) $insights[] = 'Lead conversion rate is '.$conversionRate.'%.';
        if ($activity['followups_overdue'] > 0) $insights[] = $activity['followups_overdue'].' follow-ups are overdue.';

        // Export handling
        if ($request->query('export') === 'csv') {
            $filename = 'performance-'.$start->format('Ymd').'-'.$end->format('Ymd').'.csv';
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ];

            $callback = function () use ($orders) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, ['Order ID', 'Date', 'Customer', 'Status', 'Total']);
                foreach ($orders as $order) {
                    fputcsv($handle, [
                        $order->id,
                        $order->created_at?->format('Y-m-d'),
                        $order->customer?->name,
                        $order->status,
                        $order->total_amount,
                    ]);
                }
                fclose($handle);
            };

            return response()->stream($callback, 200, $headers);
        }

        $metrics = [
            'gross' => $gross,
            'completed_value' => $completedValue,
            'order_count' => $orderCount,
            'delivered_count' => $deliveredCount,
            'cancelled' => $cancelled,
            'returned' => $returned,
            'customers_acquired' => $customersAcquired,
            'leads_generated' => $leadsGenerated,
            'leads_converted' => $leadsConverted,
            'repeat_customers' => $repeatCustomers,
            'avg_order_value' => $avgOrderValue,
            'growth' => $growth,
            'conversion_rate' => $conversionRate,
            'target' => $target,
            'target_progress' => $targetProgress,
            'commission_earned' => $commissionEarned,
            'commission_pending' => $commissionPending,
            'commission_eligible' => $commissionEligible,
        ];

        return view('agent.performance.index', compact(
            'agent', 'start', 'end', 'metrics', 'daily', 'funnel', 'topCustomers',
            'productPerformance', 'categoryPerformance', 'activity', 'insights'
        ));
    }
}
