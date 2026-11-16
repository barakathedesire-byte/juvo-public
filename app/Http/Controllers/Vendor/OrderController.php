<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Vendor\OrderWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use Yajra\DataTables\Facades\DataTables;

class OrderController extends Controller
{
    public function __construct(protected OrderWorkflowService $workflow) {}

    public const TAB_STATUSES = [
        'pending' => ['pending'],
        'processing' => ['accepted', 'processing', 'packed', 'ready_for_pickup'],
        'in_transit' => ['dispatched', 'rider_arrived'],
        'completed' => ['delivered', 'completed'],
        'canceled' => ['canceled', 'returned'],
    ];

    public function index()
    {
        $vendorId = Auth::guard('vendor')->id();

        $counts = ['all' => $this->vendorOrdersQuery($vendorId)->count()];
        foreach (self::TAB_STATUSES as $tab => $statuses) {
            $counts[$tab] = $this->vendorOrdersQuery($vendorId)->whereIn('status', $statuses)->count();
        }

        return view('vendor.orders.index', compact('counts'));
    }

    public function getData(Request $request)
    {
        $vendorId = Auth::guard('vendor')->id();

        $query = $this->vendorOrdersQuery($vendorId)
            ->with(['details.product', 'customer'])
            ->latest();

        $tabs = self::TAB_STATUSES;

        $status = $request->input('status');
        if ($status && isset($tabs[$status])) {
            $query->whereIn('status', $tabs[$status]);
        }

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('customer', function (Order $order) {
                if ($order->customer) {
                    return $order->customer->name.' ('.$order->customer->email.')';
                } elseif ($order->guest_email) {
                    return $order->guest_email.' (Guest)';
                }

                return 'N/A';
            })
            ->addColumn('order_date', fn (Order $order) => $order->created_at?->format('Y-m-d H:i'))
            ->addColumn('total_price', fn (Order $order) => number_format((float) $order->total_amount, 2))
            ->editColumn('status', fn (Order $order) => $order->statusLabel())
            ->addColumn('action', function (Order $order) {
                return '
                    <a href="'.route('vendor.orders.show', $order->id).'" class="btn btn-sm btn-outline-primary me-1">
                        <i class="fa-solid fa-eye"></i> View
                    </a>
                    <form action="'.route('vendor.orders.destroy', $order->id).'" method="POST" class="d-inline delete-order-form">
                        '.csrf_field().'
                        '.method_field('DELETE').'
                        <button type="submit" class="btn btn-danger btn-sm"
                            onclick="return confirm(\'Are you sure you want to delete this order?\')">
                            <i class="fa-solid fa-trash"></i> Delete
                        </button>
                    </form>
                ';
            })
            ->rawColumns(['action'])
            ->setRowId('id')
            ->make(true);
    }

    public function show($id)
    {
        $order = $this->vendorOrder($id);
        $order->load(['details.product.translation', 'details.variant', 'customer', 'shippingAddress', 'statusHistories']);

        $transitions = Order::STATUS_TRANSITIONS[$order->status] ?? [];

        return view('vendor.orders.show', compact('order', 'transitions'));
    }

    public function updateStatus(Request $request, $id)
    {
        $data = $request->validate([
            'status' => 'required|string',
            'reason' => 'nullable|string|max:500',
        ]);

        $order = $this->vendorOrder($id);

        try {
            $this->workflow->transition(
                $order,
                $data['status'],
                $data['reason'] ?? null,
                'vendor',
                Auth::guard('vendor')->id()
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Order #'.$order->id.' moved to '.str_replace('_', ' ', $data['status']).'.');
    }

    public function updateTracking(Request $request, $id)
    {
        $data = $request->validate([
            'shipping_method' => 'nullable|string|max:255',
            'carrier' => 'nullable|string|max:255',
            'tracking_number' => 'nullable|string|max:255',
        ]);

        $order = $this->vendorOrder($id);
        $order->update($data);

        return back()->with('success', 'Tracking details updated.');
    }

    public function bulkAction(Request $request)
    {
        $data = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
            'action' => 'required|in:accept,process,dispatch,deliver,cancel,complete',
        ]);

        $map = [
            'accept' => 'accepted',
            'process' => 'processing',
            'dispatch' => 'dispatched',
            'deliver' => 'delivered',
            'cancel' => 'canceled',
            'complete' => 'completed',
        ];
        $to = $map[$data['action']];

        $vendorId = Auth::guard('vendor')->id();
        $updated = 0;
        $skipped = 0;

        foreach ($data['ids'] as $id) {
            $order = $this->vendorOrdersQuery($vendorId)->find($id);

            if (! $order) {
                $skipped++;

                continue;
            }

            try {
                $this->workflow->transition($order, $to, 'Bulk '.$data['action'], 'vendor', $vendorId);
                $updated++;
            } catch (RuntimeException) {
                $skipped++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => $updated.' order(s) updated, '.$skipped.' skipped.',
        ]);
    }

    public function document($id, $type = 'invoice')
    {
        if (! in_array($type, ['invoice', 'receipt', 'packing_slip'], true)) {
            $type = 'invoice';
        }

        $order = $this->vendorOrder($id);
        $order->load(['details.product.translation', 'customer', 'shippingAddress', 'vendor']);

        return view('vendor.orders.document', compact('order', 'type'));
    }

    public function destroy($id)
    {
        $order = $this->vendorOrder($id);
        $order->delete();

        return response()->json([
            'success' => true,
            'message' => __('cms.orders.deleted_success'),
        ]);
    }

    protected function vendorOrder($id): Order
    {
        $vendorId = Auth::guard('vendor')->id();

        return $this->vendorOrdersQuery($vendorId)->findOrFail($id);
    }

    protected function vendorOrdersQuery($vendorId)
    {
        return Order::where(function ($query) use ($vendorId) {
            $query->where('vendor_id', $vendorId)
                ->orWhereHas('details.product', fn ($q) => $q->where('vendor_id', $vendorId));
        });
    }
}
