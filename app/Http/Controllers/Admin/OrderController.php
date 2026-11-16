<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\Access\AdminTabRegistry;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class OrderController extends Controller
{
    public function index()
    {
        return view('admin.orders.index');
    }

    public function getData(Request $request)
    {
        $query = Order::query()->latest()->with('customer');

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('customer', function (Order $order) {
                if ($order->customer) {
                    return $order->customer->name.' ('.$order->customer->email.')';
                }

                return $order->guest_email ?? 'Guest';
            })
            ->addColumn('order_date', function (Order $order) {
                return $order->created_at?->format('Y-m-d H:i');
            })
            ->addColumn('total_price', function (Order $order) {
                return number_format((float) $order->total_amount, 2);
            })
            ->editColumn('status', function (Order $order) {
                return $order->statusLabel();
            })
            ->addColumn('action', function (Order $order) {
                if (! AdminTabRegistry::canWrite('orders')) {
                    return '';
                }

                $readyForPickup = '
                    <span class="border border-primary rounded-3 d-inline-block me-1"
                          title="'.e(__('cms.orders.ready_for_pickup')).'"
                          onclick="markReadyForPickup('.$order->id.')">
                        <i class="bi bi-truck text-primary"></i>
                    </span>
                ';
                $resend = '
                    <span class="border border-secondary rounded-3 d-inline-block me-1"
                          title="'.e(__('cms.orders.resend_to_fleetbase')).'"
                          onclick="resendToFleetbase('.$order->id.')">
                        <i class="bi bi-arrow-repeat text-secondary"></i>
                    </span>
                ';
                $delete = '
                    <span class="border border-danger dt-trash rounded-3 d-inline-block" onclick="deleteOrder('.$order->id.')">
                        <i class="bi bi-trash-fill text-danger"></i>
                    </span>
                ';

                return $readyForPickup.$resend.$delete;
            })
            ->rawColumns(['action'])
            ->setRowId('id')
            ->make(true);
    }

    public function destroy($id)
    {
        abort_unless(AdminTabRegistry::canWrite('orders'), 403);

        $order = Order::findOrFail($id);
        $order->delete();

        return response()->json(['success' => true, 'message' => __('cms.orders.deleted_success')]);
    }
}
