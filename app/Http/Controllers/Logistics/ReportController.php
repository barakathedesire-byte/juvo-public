<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Logistics\LogisticsZone;
use App\Models\Order;
use App\Models\Rider\Rider;
use App\Models\ShippingAddress;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index()
    {
        $this->canView();

        $zones = LogisticsZone::orderBy('name')->get();
        $riders = Rider::orderBy('first_name')->get(['id', 'first_name', 'last_name']);
        $locations = \App\Models\Location::with('region')->active()->ordered()->get();

        return view('logistics.reports.index', compact('zones', 'riders', 'locations'));
    }

    public function generate(Request $request)
    {
        $this->canView();

        $type = $request->string('type', 'orders')->toString();
        $from = $request->date('from') ?: now()->subDays(30)->startOfDay();
        $to = $request->date('to') ?: now()->endOfDay();
        $export = $request->string('export')->toString();

        if ($type === 'riders') {
            $rows = $this->riderRows($from, $to, $request);
            $headers = ['Rider', 'Completed', 'Failed', 'Average minutes'];
        } elseif ($type === 'zones') {
            $rows = $this->zoneRows($from, $to);
            $headers = ['Zone', 'Locations', 'Orders', 'Completed', 'Success rate'];
        } elseif ($type === 'cod') {
            $rows = $this->codRows($from, $to);
            $headers = ['Order', 'Rider', 'Expected', 'Collected', 'Remitted', 'Outstanding', 'Status'];
        } else {
            $rows = $this->orderRows($from, $to, $request);
            $headers = ['Order', 'Tracking', 'Status', 'Region', 'City', 'Delivery fee', 'Total', 'Created'];
        }

        if ($export === 'csv') {
            return $this->csv($type, $headers, $rows);
        }

        return view('logistics.reports.index', [
            'zones' => LogisticsZone::orderBy('name')->get(),
            'riders' => Rider::orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            'locations' => \App\Models\Location::with('region')->active()->ordered()->get(),
            'reportType' => $type,
            'reportHeaders' => $headers,
            'reportRows' => array_slice($rows, 0, 100),
            'from' => $from,
            'to' => $to,
        ]);
    }

    private function orderRows($from, $to, Request $request)
    {
        $query = Order::with('shippingAddress')
            ->whereNotNull('logistics_tracking_id')
            ->whereBetween('created_at', [$from, $to]);

        if ($request->filled('status')) {
            $query->where('logistics_status', $request->string('status'));
        }
        if ($request->filled('rider_id')) {
            $query->where('assigned_driver_id', $request->integer('rider_id'));
        }

        return $query->latest()->limit(5000)->get()->map(fn ($order) => [
            $order->id,
            $order->logistics_tracking_id,
            $order->logistics_status,
            $order->shippingAddress?->state,
            $order->shippingAddress?->city,
            (float) $order->delivery_fee,
            (float) $order->total_amount,
            optional($order->created_at)->format('Y-m-d H:i:s'),
        ])->toArray();
    }

    private function riderRows($from, $to, Request $request)
    {
        return Rider::query()
            ->withCount(['assignedOrders as completed' => fn ($q) => $q->where('logistics_status', 'delivered')->whereBetween('delivered_at', [$from, $to])])
            ->withCount(['assignedOrders as failed' => fn ($q) => $q->where('logistics_status', 'failed')->whereBetween('created_at', [$from, $to])])
            ->get()
            ->map(fn ($rider) => [
                $rider->full_name,
                $rider->completed,
                $rider->failed,
                null,
            ])->toArray();
    }

    private function zoneRows($from, $to)
    {
        return LogisticsZone::with('locations')->get()->map(function ($zone) use ($from, $to) {
            $locationIds = $zone->locations->pluck('id');
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
                $zone->name,
                $zone->locations->count(),
                $orders,
                $completed,
                $orders > 0 ? round(($completed / $orders) * 100, 1) : 0,
            ];
        })->toArray();
    }

    private function codRows($from, $to)
    {
        return Order::with('assignedRider')
            ->whereNotNull('cod_collected_at')
            ->whereBetween('created_at', [$from, $to])
            ->latest()
            ->limit(5000)
            ->get()
            ->map(fn ($order) => [
                $order->id,
                $order->assignedRider?->full_name,
                (float) ($order->cod_amount ?? $order->total_amount),
                (float) ($order->cod_amount ?? 0),
                (float) ($order->cod_remitted_amount ?? 0),
                $order->codOutstanding(),
                $order->cod_remitted_at ? 'Reconciled' : 'Outstanding',
            ])->toArray();
    }

    private function csv(string $type, array $headers, array $rows)
    {
        $filename = 'logistics-'.$type.'-report-'.now()->format('Ymd-His').'.csv';
        $callback = function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function canView(): void
    {
        $admin = auth('logistics_admin')->user();
        abort_unless($admin && ($admin->isSuperAdmin() || $admin->canAccessLogisticsModule('analytics')), 403);
    }
}
