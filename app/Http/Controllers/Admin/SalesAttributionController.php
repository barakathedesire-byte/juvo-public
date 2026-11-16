<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SalesAttribution;
use App\Models\SalesRep;
use App\Services\Admin\ApprovalService;
use App\Services\Admin\AuditLogger;
use App\Services\Sales\SalesAttributionService;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class SalesAttributionController extends Controller
{
    public function __construct(
        protected SalesAttributionService $sales,
        protected ApprovalService $approvals,
        protected AuditLogger $audit,
    ) {}

    public function index()
    {
        return view('admin.sales.attribution.index', [
            'summary' => $this->sales->summary(),
            'reps' => SalesRep::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function getData(Request $request)
    {
        if (! $request->ajax()) {
            return;
        }

        $attributions = SalesAttribution::with(['rep', 'order'])->select('sales_attributions.*');

        if ($status = $request->input('status')) {
            $attributions->where('status', $status);
        }

        return DataTables::of($attributions)
            ->addColumn('order_ref', fn ($row) => '#'.$row->order_id)
            ->addColumn('rep', fn ($row) => $row->rep->name ?? '—')
            ->addColumn('action', function ($row) {
                if ($row->status !== 'pending') {
                    return '—';
                }

                return '<div class="d-flex gap-1">'
                    .'<form method="POST" action="'.route('admin.sales.attribution.approve', $row->id).'">'.csrf_field().method_field('PUT').'<button class="btn btn-sm btn-outline-success">Approve</button></form>'
                    .'<form method="POST" action="'.route('admin.sales.attribution.reject', $row->id).'">'.csrf_field().method_field('PUT').'<button class="btn btn-sm btn-outline-danger">Reject</button></form>'
                    .'</div>';
            })
            ->rawColumns(['action'])
            ->make(true);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'sales_rep_id' => ['required', 'integer', 'exists:sales_reps,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $order = Order::findOrFail($data['order_id']);
        $rep = SalesRep::findOrFail($data['sales_rep_id']);
        $existing = SalesAttribution::where('order_id', $order->id)->first();

        $isOverride = $existing && $existing->sales_rep_id !== $rep->id;

        if ($isOverride && $this->approvals->requiresApproval('sales.attribution.override')) {
            $approved = $this->approvals->approvedFor('sales.attribution.override', $order);

            if (! $approved) {
                $pending = $this->approvals->pendingFor('sales.attribution.override', $order)
                    ?? $this->approvals->submit(
                        auth()->user(),
                        'sales.attribution.override',
                        $order,
                        ['order_id' => $order->id, 'sales_rep_id' => $rep->id],
                        $data['notes'] ?? null,
                    );

                return back()->with('success', 'Reassigning this order is awaiting approval ('.$pending->reference.').');
            }
        }

        $attribution = $this->sales->attribute($order, $rep, $isOverride ? 'override' : 'manual', auth()->id(), $data['notes'] ?? null);

        if (isset($approved) && $approved) {
            $this->approvals->markExecuted($approved, auth()->user());
        }

        $this->audit->log('sales.attribution.created', $attribution, [], [
            'order_id' => $order->id,
            'sales_rep_id' => $rep->id,
            'commission_amount' => $attribution->commission_amount,
        ], $data['notes'] ?? null);

        return back()->with('success', 'Order attributed to '.$rep->name.'.');
    }

    public function approve(SalesAttribution $attribution)
    {
        $this->sales->approve($attribution, auth()->id());
        $this->audit->log('sales.attribution.approved', $attribution, ['status' => 'pending'], ['status' => 'approved']);

        return back()->with('success', 'Attribution approved.');
    }

    public function reject(SalesAttribution $attribution)
    {
        $this->sales->reject($attribution, auth()->id());
        $this->audit->log('sales.attribution.rejected', $attribution, ['status' => 'pending'], ['status' => 'rejected']);

        return back()->with('success', 'Attribution rejected.');
    }
}
