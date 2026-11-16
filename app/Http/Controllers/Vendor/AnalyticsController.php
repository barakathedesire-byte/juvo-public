<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Refund;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $vendor = Auth::guard('vendor')->user();
        [$from, $to] = $this->range($request);

        return view('vendor.analytics.index', array_merge(
            $this->report($vendor->id, $from, $to),
            ['vendor' => $vendor, 'from' => $from, 'to' => $to]
        ));
    }

    public function export(Request $request): StreamedResponse
    {
        $vendor = Auth::guard('vendor')->user();
        [$from, $to] = $this->range($request);
        $report = $this->report($vendor->id, $from, $to);

        $filename = 'analytics-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['Daily sales']);
            fputcsv($out, ['Date', 'Orders', 'Revenue']);
            foreach ($report['daily'] as $row) {
                fputcsv($out, [$row->day, $row->orders, number_format((float) $row->revenue, 2, '.', '')]);
            }

            fputcsv($out, []);
            fputcsv($out, ['Top products']);
            fputcsv($out, ['Product', 'Units', 'Revenue']);
            foreach ($report['topProducts'] as $row) {
                fputcsv($out, [$row->name, $row->units, number_format((float) $row->revenue, 2, '.', '')]);
            }

            fputcsv($out, []);
            fputcsv($out, ['Summary']);
            foreach ($report['kpis'] as $label => $value) {
                fputcsv($out, [ucwords(str_replace('_', ' ', $label)), $value]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    protected function range(Request $request): array
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'))->startOfDay()
            : now()->subDays(29)->startOfDay();
        $to = $request->filled('to')
            ? Carbon::parse($request->input('to'))->endOfDay()
            : now()->endOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }

    /**
     * @return array<string,mixed>
     */
    protected function report(int $vendorId, Carbon $from, Carbon $to): array
    {
        $range = [$from, $to];

        $completed = Order::where('vendor_id', $vendorId)
            ->where('status', 'completed')
            ->whereBetween('created_at', $range);

        $grossSales = (float) (clone $completed)->sum('total_amount');
        $completedOrders = (clone $completed)->count();
        $unitsSold = (int) DB::table('order_details')
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->where('orders.vendor_id', $vendorId)
            ->where('orders.status', 'completed')
            ->whereBetween('orders.created_at', $range)
            ->sum('order_details.quantity');

        $refunds = (float) Refund::where('vendor_id', $vendorId)
            ->whereBetween('created_at', $range)
            ->where('status', 'approved')
            ->sum('amount');

        $allOrders = (int) Order::where('vendor_id', $vendorId)->whereBetween('created_at', $range)->count();
        $cancelled = (int) Order::where('vendor_id', $vendorId)->where('status', 'canceled')->whereBetween('created_at', $range)->count();

        $avgOrderValue = $completedOrders > 0 ? round($grossSales / $completedOrders, 2) : 0.0;

        $daily = Order::where('vendor_id', $vendorId)
            ->where('status', 'completed')
            ->whereBetween('created_at', $range)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as orders, SUM(total_amount) as revenue')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $topProducts = DB::table('order_details')
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->where('orders.vendor_id', $vendorId)
            ->where('orders.status', 'completed')
            ->whereBetween('orders.created_at', $range)
            ->selectRaw('order_details.product_id, SUM(order_details.quantity) as units, SUM(order_details.quantity * order_details.price) as revenue')
            ->groupBy('order_details.product_id')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();

        $names = Product::with('translation')->whereIn('id', $topProducts->pluck('product_id'))->get()->keyBy('id');
        $topProducts = $topProducts->map(function ($row) use ($names) {
            $row->name = $names[$row->product_id]->translation->name ?? 'Product #'.$row->product_id;

            return $row;
        });

        $statusBreakdown = Order::where('vendor_id', $vendorId)
            ->whereBetween('created_at', $range)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->orderByDesc('total')
            ->get();

        $maxDaily = max(1.0, (float) $daily->max('revenue'));

        return [
            'kpis' => [
                'gross_sales' => round($grossSales, 2),
                'completed_orders' => $completedOrders,
                'units_sold' => $unitsSold,
                'average_order_value' => $avgOrderValue,
                'refunds' => round($refunds, 2),
                'net_sales' => round($grossSales - $refunds, 2),
                'total_orders' => $allOrders,
                'cancelled_orders' => $cancelled,
                'conversion_rate' => $allOrders > 0 ? round(($completedOrders / $allOrders) * 100, 1) : 0.0,
            ],
            'daily' => $daily,
            'maxDaily' => $maxDaily,
            'topProducts' => $topProducts,
            'statusBreakdown' => $statusBreakdown,
        ];
    }
}
