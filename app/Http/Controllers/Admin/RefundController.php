<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Refund;
use App\Services\Access\AuthorizationService;
use App\Services\Vendor\OrderWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\Facades\DataTables;

class RefundController extends Controller
{
    public function __construct(protected AuthorizationService $auth) {}

    public function index()
    {
        return view('admin.refunds.index');
    }

    public function getData(Request $request)
    {
        if ($request->ajax()) {
            $this->auth->authorize($request->user(), 'manage-finances');

            $refunds = Refund::with('payment', 'order', 'customer')->select('refunds.*');

            return DataTables::of($refunds)
                ->addColumn('payment', fn ($row) => $row->payment ? 'Payment #'.$row->payment->id : '—')
                ->addColumn('order', fn ($row) => $row->order ? 'Order #'.$row->order->id : '—')
                ->addColumn('customer', fn ($row) => $row->customer->name ?? '—')
                ->addColumn('action', function ($row) {
                    $html = '
                        <a href="'.route('admin.refunds.show', $row->id).'" 
                        class="border border-primary dt-show rounded-3 d-inline-block me-1 px-2 py-1">
                            <i class="bi bi-eye-fill text-primary"></i>
                        </a>';

                    if ($row->status === 'requested') {
                        $html .= '
                        <span class="border border-success rounded-3 d-inline-block me-1 px-2 py-1" 
                            title="Approve" onclick="approveRefund('.$row->id.')"> 
                            <i class="bi bi-check-lg text-success"></i>
                        </span>
                        <span class="border border-warning rounded-3 d-inline-block me-1 px-2 py-1" 
                            title="Reject" onclick="rejectRefund('.$row->id.')"> 
                            <i class="bi bi-x-lg text-warning"></i>
                        </span>';
                    }

                    $html .= '
                        <span class="border border-danger dt-trash rounded-3 d-inline-block" 
                            onclick="deleteRefund('.$row->id.')"> 
                            <i class="bi bi-trash-fill text-danger"></i>
                        </span>
                    ';

                    return $html;
                })
                ->make(true);
        }
    }

    public function show($id)
    {
        $this->auth->authorize(auth()->user(), 'manage-finances');

        $refund = Refund::with('payment.user', 'payment.order', 'payment.gateway', 'order', 'customer', 'vendor')->findOrFail($id);

        return view('admin.refunds.show', compact('refund'));
    }

    public function approve(Request $request, Refund $refund)
    {
        $this->auth->authorize($request->user(), 'manage-finances', ['write' => true]);

        if ($refund->status !== 'requested') {
            return back()->with('error', 'Only pending return requests can be approved.');
        }

        $data = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $refund->update([
            'status' => 'approved',
            'admin_note' => $data['admin_note'] ?? null,
        ]);

        $order = $refund->order;

        if ($order && $order->canTransitionTo('returned')) {
            try {
                app(OrderWorkflowService::class)->transition($order, 'returned', 'Return approved', 'admin', Auth::id());
            } catch (\Throwable $e) {
                \Log::warning('Order return transition failed for order #'.$order->id.': '.$e->getMessage());
            }
        }

        try {
            app(\App\Services\Store\RefundService::class)->settle($refund);
        } catch (\Throwable $e) {
            \Log::warning('Refund settlement failed for refund #'.$refund->id.': '.$e->getMessage());
        }

        return back()->with('success', 'Return request approved.');
    }

    public function reject(Request $request, Refund $refund)
    {
        $this->auth->authorize($request->user(), 'manage-finances', ['write' => true]);

        if ($refund->status !== 'requested') {
            return back()->with('error', 'Only pending return requests can be rejected.');
        }

        $data = $request->validate([
            'admin_note' => ['required', 'string', 'max:1000'],
        ]);

        $refund->update([
            'status' => 'rejected',
            'admin_note' => $data['admin_note'],
        ]);

        return back()->with('success', 'Return request rejected.');
    }

    public function destroy(Refund $refund)
    {
        $this->auth->authorize(auth()->user(), 'manage-finances', ['write' => true]);

        $refund->delete();

        return response()->json(['success' => true, 'message' => 'Refund deleted successfully.']);
    }
}
