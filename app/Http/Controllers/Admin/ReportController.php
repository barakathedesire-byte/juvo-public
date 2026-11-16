<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        [$from, $to] = $this->range($request);

        $orders = Order::whereBetween('created_at', [$from, $to]);

        $summary = [
            'orders' => (clone $orders)->count(),
            'revenue' => (float) (clone $orders)->whereNotIn('status', ['canceled'])->sum('total_amount'),
            'paid' => (float) (clone $orders)->where('payment_status', 'paid')->sum('total_amount'),
            'discount' => (float) (clone $orders)->sum('discount_amount'),
            'tax' => (float) (clone $orders)->sum('tax_amount'),
            'shipping' => (float) (clone $orders)->sum('delivery_fee'),
            'commission' => (float) (clone $orders)->sum('commission_total'),
        ];
        $summary['avg_order'] = $summary['orders'] > 0 ? round($summary['revenue'] / $summary['orders'], 2) : 0;

        $byDay = (clone $orders)
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('COUNT(*) as orders'), DB::raw('SUM(total_amount) as revenue'))
            ->groupBy('day')->orderBy('day')
            ->get();

        $topProducts = OrderDetail::query()
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->whereBetween('orders.created_at', [$from, $to])
            ->whereNotIn('orders.status', ['canceled'])
            ->select('order_details.product_id', DB::raw('SUM(order_details.quantity) as qty'), DB::raw('SUM(order_details.quantity * order_details.price) as revenue'))
            ->groupBy('order_details.product_id')
            ->orderByDesc('revenue')->limit(10)
            ->get();

        $productNames = \App\Models\Product::with('translations')
            ->whereIn('id', $topProducts->pluck('product_id')->filter())
            ->get()
            ->mapWithKeys(fn ($p) => [$p->id => $p->translation->name ?? ('Product #'.$p->id)]);
        $topProducts->each(fn ($row) => $row->product_name = $productNames[$row->product_id] ?? ('Product #'.$row->product_id));

        $topVendors = Order::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereNotIn('status', ['canceled'])
            ->select('vendor_id', DB::raw('COUNT(*) as orders'), DB::raw('SUM(total_amount) as revenue'), DB::raw('SUM(commission_total) as commission'))
            ->groupBy('vendor_id')->orderByDesc('revenue')->limit(10)
            ->get();

        $vendorNames = Vendor::whereIn('id', $topVendors->pluck('vendor_id')->filter())->pluck('name', 'id');

        return view('admin.reports.index', [
            'from' => $from, 'to' => $to, 'summary' => $summary,
            'byDay' => $byDay, 'topProducts' => $topProducts,
            'topVendors' => $topVendors->map(function ($row) use ($vendorNames) {
                $row->vendor_name = $vendorNames[$row->vendor_id] ?? ('Vendor #'.$row->vendor_id);

                return $row;
            }),
        ]);
    }

    public function export(Request $request)
    {
        [$from, $to] = $this->range($request);

        $orders = Order::with('vendor')->whereBetween('created_at', [$from, $to])->orderBy('id')->get();

        $csv = "id,date,status,payment_status,customer_id,vendor,subtotal,discount,tax,shipping,total,commission,net,currency\n";
        foreach ($orders as $o) {
            $csv .= implode(',', [
                $o->id,
                $o->created_at->toDateTimeString(),
                $o->status,
                $o->payment_status,
                $o->customer_id ?? $o->guest_email ?? '',
                '"'.str_replace('"', '""', $o->vendor->name ?? '').'"',
                $o->subtotal, $o->discount_amount, $o->tax_amount, $o->delivery_fee,
                $o->total_amount, $o->commission_total, $o->net_vendor_amount, $o->currency,
            ])."\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="sales-report-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv"',
        ]);
    }

    /**
     * @return array{0:\Illuminate\Support\Carbon,1:\Illuminate\Support\Carbon}
     */
    protected function range(Request $request): array
    {
        $from = $request->filled('from')
            ? \Illuminate\Support\Carbon::parse($request->input('from'))->startOfDay()
            : now()->subDays(29)->startOfDay();

        $to = $request->filled('to')
            ? \Illuminate\Support\Carbon::parse($request->input('to'))->endOfDay()
            : now()->endOfDay();

        return [$from, $to];
    }
}
