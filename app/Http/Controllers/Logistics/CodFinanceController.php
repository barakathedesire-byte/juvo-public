<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Logistics\CodAdjustment;
use App\Models\Logistics\CodHandover;
use App\Models\Logistics\CodHandoverAllocation;
use App\Models\Logistics\DeliveryEvent;
use App\Models\Order;
use App\Models\Rider\Rider;
use App\Services\Admin\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CodFinanceController extends Controller
{
    public function index(Request $request)
    {
        $this->canFinance();

        $search = $request->string('q')->toString();
        $riderId = $request->integer('rider_id');
        $status = $request->string('status')->toString();
        $zone = $request->string('zone')->toString();
        $dateFrom = $request->string('date_from')->toString();
        $dateTo = $request->string('date_to')->toString();

        $baseQuery = Order::query()
            ->where('logistics_status', 'delivered')
            ->whereIn('payment_method', ['cod', 'payondelivery', 'pod', 'pay_on_delivery'])
            ->with(['assignedRider', 'customer', 'codAdjustments', 'codHandovers.handover']);

        $transactions = (clone $baseQuery)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('id', $search)->orWhere('logistics_tracking_id', 'like', "%{$search}%");
                });
            })
            ->when($riderId, fn ($query) => $query->where('assigned_driver_id', $riderId))
            ->when($zone !== '', function ($query) use ($zone) {
                $query->where(function ($inner) use ($zone) {
                    $inner->whereHas('assignedRider', fn ($rider) => $rider->where('city', 'like', "%{$zone}%")->orWhere('state', 'like', "%{$zone}%"))
                        ->orWhereHas('shippingAddress', fn ($address) => $address->where('city', 'like', "%{$zone}%")->orWhere('state', 'like', "%{$zone}%"));
                });
            })
            ->when($dateFrom !== '', fn ($query) => $query->whereRaw('DATE(COALESCE(cod_collected_at, delivered_at)) >= ?', [$dateFrom]))
            ->when($dateTo !== '', fn ($query) => $query->whereRaw('DATE(COALESCE(cod_collected_at, delivered_at)) <= ?', [$dateTo]))
            ->when($status === 'awaiting_collection', fn ($query) => $query->whereNull('cod_collected_at'))
            ->when($status === 'outstanding', fn ($query) => $query->whereNotNull('cod_collected_at')
                ->whereRaw('(COALESCE(cod_amount, total_amount) - COALESCE(cod_remitted_amount, 0)) > 0'))
            ->when($status === 'reconciled', fn ($query) => $query->where(function ($inner) {
                $inner->whereNotNull('cod_remitted_at')
                    ->orWhereRaw('COALESCE(cod_amount, total_amount) <= COALESCE(cod_remitted_amount, 0)');
            }))
            ->when($status === 'pending_verification', fn ($query) => $query->whereHas('codHandovers.handover', fn ($handover) => $handover->where('status', 'pending_verification')))
            ->when($status === 'disputed', fn ($query) => $query->where(function ($inner) {
                $inner->whereHas('codHandovers.handover', fn ($handover) => $handover->whereIn('status', ['rejected', 'disputed']))
                    ->orWhereHas('codAdjustments', fn ($adjustment) => $adjustment->whereIn('status', ['pending_approval', 'rejected']));
            }))
            ->orderByRaw('COALESCE(cod_collected_at, delivered_at) DESC')
            ->paginate(25, ['*'], 'transactions')
            ->withQueryString();

        $awaitingBase = (clone $baseQuery)->whereNull('cod_collected_at');
        $awaitingCount = (clone $awaitingBase)->count();
        $awaitingTotal = (float) (clone $awaitingBase)->sum('total_amount');
        $awaitingCollection = (clone $awaitingBase)
            ->latest('delivered_at')
            ->limit(40)
            ->get();

        $recentlyReconciled = CodHandover::with(['rider', 'allocations.order'])
            ->latest()
            ->limit(15)
            ->get();

        $pendingHandovers = CodHandover::with(['rider', 'allocations'])
            ->where('status', 'pending_verification')
            ->latest()
            ->limit(20)
            ->get();

        $pendingAdjustments = CodAdjustment::with(['order', 'rider'])
            ->where('status', 'pending_approval')
            ->latest()
            ->limit(20)
            ->get();

        $byRider = Order::query()
            ->selectRaw('assigned_driver_id, COUNT(*) as orders, SUM(COALESCE(cod_amount, total_amount)) as amount')
            ->where('logistics_status', 'delivered')
            ->whereNotNull('cod_collected_at')
            ->whereIn('payment_method', ['cod', 'payondelivery', 'pod', 'pay_on_delivery'])
            ->whereNotNull('assigned_driver_id')
            ->groupBy('assigned_driver_id')
            ->get()
            ->map(function ($row) {
                $row->rider = Rider::find($row->assigned_driver_id);

                $remitted = Order::where('assigned_driver_id', $row->assigned_driver_id)
                    ->whereNotNull('cod_collected_at')
                    ->sum(DB::raw('COALESCE(cod_remitted_amount,0)'));
                $adjustments = CodAdjustment::where('rider_id', $row->assigned_driver_id)
                    ->where('status', 'approved')
                    ->get()
                    ->sum(fn ($adj) => $adj->direction === 'decrease' ? (float) $adj->amount : -(float) $adj->amount);
                $collected = (float) $row->amount;
                $row->remitted = (float) $remitted;
                $row->adjustments = (float) $adjustments;
                $row->outstanding = max(0.0, $collected - $row->remitted - $row->adjustments);
                $row->collected = $collected;

                return $row;
            })
            ->filter(fn ($row) => $row->rider !== null && $row->outstanding > 0)
            ->sortByDesc('outstanding')
            ->values();

        $riders = Rider::where('is_active', true)->orderBy('first_name')->get(['id', 'first_name', 'last_name']);
        $zones = Rider::where('is_active', true)->whereNotNull('city')->distinct()->orderBy('city')->pluck('city');

        $expected = (float) (clone $baseQuery)->sum('total_amount');
        $collected = (float) (clone $baseQuery)->whereNotNull('cod_collected_at')->sum('cod_amount');
        $remittedBase = (float) (clone $baseQuery)->whereNotNull('cod_collected_at')
            ->selectRaw('COALESCE(SUM(COALESCE(cod_amount, total_amount) - COALESCE(cod_remitted_amount, 0)),0) as amount')
            ->value('amount');
        $adjustmentNet = (float) CodAdjustment::where('status', 'approved')
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'decrease' THEN amount ELSE -amount END),0) as net")
            ->value('net');
        $submitted = (float) CodHandover::whereIn('status', ['pending_verification', 'verified'])->sum('amount');
        $awaitingVerification = (float) CodHandover::where('status', 'pending_verification')->sum('amount');
        $reconciled = (float) CodHandover::where('status', 'verified')->sum('amount');
        $disputed = (float) CodHandover::whereIn('status', ['rejected', 'disputed'])->sum('amount')
            + (float) CodAdjustment::whereIn('status', ['pending_approval', 'rejected'])->sum('amount');

        $totals = [
            'expected' => $expected,
            'collected' => $collected,
            'outstanding' => max(0.0, $remittedBase - $adjustmentNet),
            'outstanding_count' => (clone $baseQuery)->whereNotNull('cod_collected_at')
                ->whereRaw('(COALESCE(cod_amount, total_amount) - COALESCE(cod_remitted_amount, 0)) > 0')->count(),
            'awaiting' => $awaitingTotal,
            'awaiting_count' => $awaitingCount,
            'submitted' => $submitted,
            'awaiting_verification' => $awaitingVerification,
            'reconciled' => $reconciled,
            'reconciled_30d' => (float) CodHandover::where('status', 'verified')
                ->where('verified_at', '>=', now()->subDays(30))->sum('amount'),
            'disputed' => $disputed,
            'pending_handovers' => $pendingHandovers->count(),
            'pending_adjustments' => $pendingAdjustments->count(),
        ];

        return view('logistics.finance.cod', compact(
            'transactions', 'awaitingCollection', 'recentlyReconciled', 'pendingHandovers',
            'pendingAdjustments', 'byRider', 'riders', 'zones', 'totals', 'search', 'status', 'riderId', 'zone', 'dateFrom', 'dateTo'
        ));
    }

    public function riderStatement(Rider $rider)
    {
        $this->canFinance();

        $rider->loadMissing('assignedOrders');

        $orders = Order::where('assigned_driver_id', $rider->id)
            ->where('logistics_status', 'delivered')
            ->whereNotNull('cod_collected_at')
            ->whereIn('payment_method', ['cod', 'payondelivery', 'pod', 'pay_on_delivery'])
            ->with(['customer', 'codAdjustments'])
            ->latest('cod_collected_at')
            ->get();

        $collected = (float) $orders->sum(fn ($order) => (float) ($order->cod_amount ?? $order->total_amount));
        $remitted = (float) $orders->sum(fn ($order) => (float) ($order->cod_remitted_amount ?? 0));
        $adjustments = CodAdjustment::where('rider_id', $rider->id)
            ->where('status', 'approved')
            ->get()
            ->sum(fn ($adj) => $adj->direction === 'decrease' ? (float) $adj->amount : -(float) $adj->amount);
        $outstanding = max(0.0, $collected - $remitted - $adjustments);

        $handovers = CodHandover::with(['allocations.order'])
            ->where('rider_id', $rider->id)
            ->latest()
            ->limit(20)
            ->get();

        $adjustmentsList = CodAdjustment::with(['order'])
            ->where('rider_id', $rider->id)
            ->latest()
            ->limit(20)
            ->get();

        return view('logistics.finance.rider-cod', compact(
            'rider', 'orders', 'handovers', 'adjustmentsList', 'collected', 'remitted', 'adjustments', 'outstanding'
        ));
    }

    public function createHandover(Request $request)
    {
        $this->canFinance();

        $data = $request->validate([
            'rider_id' => 'required|exists:riders,id',
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|in:cash,bank,mobile_money',
            'external_reference' => 'nullable|string|max:120',
            'notes' => 'nullable|string|max:1000',
            'order_ids' => 'array',
            'order_ids.*' => 'integer|exists:orders,id',
        ]);

        $rider = Rider::findOrFail($data['rider_id']);
        $admin = $this->admin();

        $handover = DB::transaction(function () use ($data, $rider, $admin) {
            $reference = 'HOV-'.strtoupper(Str::random(10));
            $handover = CodHandover::create([
                'reference' => $reference,
                'rider_id' => $rider->id,
                'amount' => $data['amount'],
                'method' => $data['method'],
                'status' => 'pending_verification',
                'external_reference' => $data['external_reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'initiated_by_type' => get_class($admin),
                'initiated_by_id' => $admin->id,
                'initiated_by_name' => $admin->name,
            ]);

            $ordersQuery = Order::where('assigned_driver_id', $rider->id)
                ->whereNotNull('cod_collected_at')
                ->whereIn('payment_method', ['cod', 'payondelivery', 'pod', 'pay_on_delivery'])
                ->when(! empty($data['order_ids']), fn ($query) => $query->whereIn('id', $data['order_ids']))
                ->orderBy('cod_collected_at');

            $remaining = (float) $data['amount'];
            foreach ($ordersQuery->lockForUpdate()->get() as $order) {
                if ($remaining <= 0) {
                    break;
                }
                $outstanding = max(0.0, (float) ($order->cod_amount ?? $order->total_amount) - (float) ($order->cod_remitted_amount ?? 0) - $order->approvedCodAdjustmentNet());
                if ($outstanding <= 0) {
                    continue;
                }
                $apply = min($outstanding, $remaining);
                CodHandoverAllocation::create([
                    'cod_handover_id' => $handover->id,
                    'order_id' => $order->id,
                    'amount' => $apply,
                ]);
                $remaining -= $apply;
            }

            if ($remaining > 0.01) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'amount' => "The handover amount exceeds the rider's collected COD balance.",
                ]);
            }

            AuditLogger::record('logistics.cod.handover_created', $handover, [], [
                'reference' => $handover->reference,
                'rider_id' => $rider->id,
                'amount' => $handover->amount,
            ], null, $admin);

            return $handover;
        });

        return redirect()->route('logistics.finance.index')->with('success', 'Cash handover created. Reference: '.$handover->reference);
    }

    public function verifyHandover(Request $request, CodHandover $handover)
    {
        $this->canFinanceApprove();

        $data = $request->validate([
            'external_reference' => 'nullable|string|max:120',
            'notes' => 'nullable|string|max:1000',
        ]);

        $admin = $this->admin();

        DB::transaction(function () use ($handover, $data, $admin) {
            $locked = CodHandover::whereKey($handover->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending_verification') {
                throw \Illuminate\Validation\ValidationException::withMessages(['handover' => 'This handover cannot be verified in its current state.']);
            }

            if ((int) $locked->initiated_by_id === (int) $admin->id && $locked->initiated_by_type === get_class($admin)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['handover' => 'A cash handover must be verified by someone other than the person who created it.']);
            }

            $locked->forceFill([
                'status' => 'verified',
                'external_reference' => $data['external_reference'] ?: $locked->external_reference,
                'notes' => $data['notes'] ?: $locked->notes,
                'verified_by_type' => get_class($admin),
                'verified_by_id' => $admin->id,
                'verified_by_name' => $admin->name,
                'verified_at' => now(),
            ])->save();

            $allocations = $locked->allocations()->with('order')->get();
            $remaining = (float) $locked->amount;

            foreach ($allocations as $allocation) {
                if ($remaining <= 0) {
                    break;
                }
                $order = Order::whereKey($allocation->order_id)->lockForUpdate()->first();
                if (! $order || $order->assigned_driver_id !== $locked->rider_id) {
                    continue;
                }
                $expected = (float) ($order->cod_amount ?? $order->total_amount);
                $remitted = (float) ($order->cod_remitted_amount ?? 0);
                $adjustments = (float) $order->approvedCodAdjustmentNet();
                $outstanding = max(0.0, $expected - $remitted - $adjustments);
                if ($outstanding <= 0) {
                    continue;
                }
                $apply = min($outstanding, $remaining);
                $newRemitted = $remitted + $apply;
                $fullyReconciled = ($expected - $newRemitted - $adjustments) <= 0.01;
                $order->forceFill([
                    'cod_remitted_amount' => $newRemitted,
                    'cod_remitted_at' => $fullyReconciled ? ($order->cod_remitted_at ?: now()) : $order->cod_remitted_at,
                    'cod_remittance_ref' => $fullyReconciled ? ($order->cod_remittance_ref ?: $locked->reference) : $order->cod_remittance_ref,
                ])->save();

                DeliveryEvent::create([
                    'order_id' => $order->id,
                    'rider_id' => $locked->rider_id,
                    'type' => DeliveryEvent::TYPE_COD_REMITTED,
                    'from_status' => $order->logistics_status,
                    'to_status' => $order->logistics_status,
                    'actor_type' => get_class($admin),
                    'actor_id' => $admin->id,
                    'actor_name' => $admin->name,
                    'note' => 'COD remitted via handover '.$locked->reference.'.',
                    'meta' => ['reference' => $locked->reference, 'amount' => $apply],
                ]);

                $remaining -= $apply;
            }

            if ($remaining > 0.01) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'handover' => 'The handover amount no longer matches the rider\'s outstanding COD balance. Reject and recreate the handover.',
                ]);
            }

            AuditLogger::record('logistics.cod.handover_verified', $locked, ['status' => 'pending_verification'], ['status' => 'verified'], $data['notes'] ?? null, $admin);
        });

        return redirect()->route('logistics.finance.index')->with('success', 'Handover verified and applied.');
    }

    public function rejectHandover(Request $request, CodHandover $handover)
    {
        $this->canFinanceApprove();

        $data = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $admin = $this->admin();

        DB::transaction(function () use ($handover, $data, $admin) {
            $locked = CodHandover::whereKey($handover->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending_verification') {
                throw \Illuminate\Validation\ValidationException::withMessages(['handover' => 'This handover cannot be rejected in its current state.']);
            }

            $locked->forceFill([
                'status' => 'rejected',
                'rejection_reason' => $data['rejection_reason'],
                'rejected_by_type' => get_class($admin),
                'rejected_by_id' => $admin->id,
                'rejected_by_name' => $admin->name,
                'rejected_at' => now(),
            ])->save();

            AuditLogger::record('logistics.cod.handover_rejected', $locked, ['status' => 'pending_verification'], ['status' => 'rejected'], $data['rejection_reason'], $admin);
        });

        return redirect()->route('logistics.finance.index')->with('success', 'Handover rejected.');
    }

    public function createAdjustment(Request $request)
    {
        $this->canFinanceApprove();

        $data = $request->validate([
            'order_id' => 'nullable|exists:orders,id',
            'rider_id' => 'nullable|exists:riders,id',
            'type' => 'required|in:discrepancy,write_off,refund,correction',
            'direction' => 'required|in:decrease,increase',
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:1000',
        ]);

        $admin = $this->admin();
        $this->assertAdjustmentWithinOutstanding($data);

        $adjustment = DB::transaction(function () use ($data, $admin) {
            $reference = 'CAD-'.strtoupper(Str::random(10));
            $adjustment = CodAdjustment::create([
                'reference' => $reference,
                'order_id' => $data['order_id'] ?? null,
                'rider_id' => $data['rider_id'] ?? null,
                'type' => $data['type'],
                'direction' => $data['direction'],
                'amount' => $data['amount'],
                'status' => 'pending_approval',
                'reason' => $data['reason'],
                'created_by_type' => get_class($admin),
                'created_by_id' => $admin->id,
                'created_by_name' => $admin->name,
            ]);

            AuditLogger::record('logistics.cod.adjustment_created', $adjustment, [], $adjustment->toArray(), $data['reason'], $admin);

            return $adjustment;
        });

        return redirect()->route('logistics.finance.index')->with('success', 'Adjustment created for approval. Reference: '.$adjustment->reference);
    }

    public function approveAdjustment(Request $request, CodAdjustment $adjustment)
    {
        $this->canFinanceApprove();

        $admin = $this->admin();

        DB::transaction(function () use ($adjustment, $admin) {
            $locked = CodAdjustment::whereKey($adjustment->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending_approval') {
                throw \Illuminate\Validation\ValidationException::withMessages(['adjustment' => 'This adjustment cannot be approved in its current state.']);
            }

            if ((int) $locked->created_by_id === (int) $admin->id && $locked->created_by_type === get_class($admin)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['adjustment' => 'A COD adjustment must be approved by someone other than the person who created it.']);
            }

            $locked->forceFill([
                'status' => 'approved',
                'approved_by_type' => get_class($admin),
                'approved_by_id' => $admin->id,
                'approved_by_name' => $admin->name,
                'approved_at' => now(),
            ])->save();

            if ($locked->order_id) {
                $order = Order::whereKey($locked->order_id)->lockForUpdate()->first();
                if ($order) {
                    DeliveryEvent::create([
                        'order_id' => $order->id,
                        'rider_id' => $order->assigned_driver_id,
                        'type' => DeliveryEvent::TYPE_NOTE,
                        'from_status' => $order->logistics_status,
                        'to_status' => $order->logistics_status,
                        'actor_type' => get_class($admin),
                        'actor_id' => $admin->id,
                        'actor_name' => $admin->name,
                        'note' => 'COD adjustment '.$locked->reference.' approved ('.($locked->direction === 'decrease' ? 'decrease' : 'increase').') '.number_format((float) $locked->amount, 2).'.',
                        'meta' => ['adjustment_id' => $locked->id, 'reference' => $locked->reference],
                    ]);
                }
            }

            AuditLogger::record('logistics.cod.adjustment_approved', $locked, ['status' => 'pending_approval'], ['status' => 'approved'], null, $admin);
        });

        return redirect()->route('logistics.finance.index')->with('success', 'Adjustment approved.');
    }

    public function rejectAdjustment(Request $request, CodAdjustment $adjustment)
    {
        $this->canFinanceApprove();

        $data = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $admin = $this->admin();

        DB::transaction(function () use ($adjustment, $data, $admin) {
            $locked = CodAdjustment::whereKey($adjustment->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending_approval') {
                throw \Illuminate\Validation\ValidationException::withMessages(['adjustment' => 'This adjustment cannot be rejected in its current state.']);
            }

            $locked->forceFill([
                'status' => 'rejected',
                'rejection_reason' => $data['rejection_reason'],
                'rejected_by_type' => get_class($admin),
                'rejected_by_id' => $admin->id,
                'rejected_by_name' => $admin->name,
                'rejected_at' => now(),
            ])->save();

            AuditLogger::record('logistics.cod.adjustment_rejected', $locked, ['status' => 'pending_approval'], ['status' => 'rejected'], $data['rejection_reason'], $admin);
        });

        return redirect()->route('logistics.finance.index')->with('success', 'Adjustment rejected.');
    }

    public function export(Request $request)
    {
        $this->canFinance();

        $orders = Order::query()
            ->where('logistics_status', 'delivered')
            ->whereNotNull('cod_collected_at')
            ->whereIn('payment_method', ['cod', 'payondelivery', 'pod', 'pay_on_delivery'])
            ->with(['assignedRider'])
            ->get();

        $headers = ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="cod_reconciliation.csv"'];

        $callback = function () use ($orders) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['order_id', 'tracking_id', 'rider', 'cod_amount', 'remitted_amount', 'adjustments', 'outstanding', 'collected_at', 'remitted_at']);
            foreach ($orders as $order) {
                $expected = (float) ($order->cod_amount ?? $order->total_amount);
                $remitted = (float) ($order->cod_remitted_amount ?? 0);
                $adjustments = (float) $order->approvedCodAdjustmentNet();
                $outstanding = max(0.0, $expected - $remitted - $adjustments);
                fputcsv($handle, [
                    $order->id,
                    $order->logistics_tracking_id,
                    $order->assignedRider ? $order->assignedRider->full_name : '',
                    $expected,
                    $remitted,
                    $adjustments,
                    $outstanding,
                    optional($order->cod_collected_at)->toDateTimeString(),
                    optional($order->cod_remitted_at)->toDateTimeString(),
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function admin()
    {
        return auth('logistics_admin')->user();
    }

    private function assertAdjustmentWithinOutstanding(array $data): void
    {
        if (($data['direction'] ?? '') !== 'decrease') {
            return;
        }

        $amount = (float) $data['amount'];
        $order = ! empty($data['order_id']) ? Order::with('codAdjustments')->find($data['order_id']) : null;

        if ($order) {
            if (! empty($data['rider_id']) && (int) $order->assigned_driver_id !== (int) $data['rider_id']) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'rider_id' => 'The selected order does not belong to the selected rider.',
                ]);
            }

            $outstanding = $order->codOutstanding();
        } elseif (! empty($data['rider_id'])) {
            $outstanding = (float) Order::where('assigned_driver_id', $data['rider_id'])
                ->whereNotNull('cod_collected_at')
                ->whereIn('payment_method', ['cod', 'payondelivery', 'pod', 'pay_on_delivery'])
                ->with('codAdjustments')
                ->get()
                ->sum(fn (Order $each) => $each->codOutstanding());
        } else {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'order_id' => 'A decrease adjustment must reference an order or a rider.',
            ]);
        }

        if ($outstanding <= 0 || $amount > $outstanding + 0.01) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'amount' => 'The adjustment cannot exceed the outstanding cash balance of TZS '.number_format($outstanding, 2).'.',
            ]);
        }
    }

    private function canFinance(): void
    {
        $admin = $this->admin();
        abort_unless($admin && (in_array($admin->role, ['super_admin', 'admin'], true) || $admin->hasPermission('logistics.finance')), 403, 'You do not have permission to view cash reconciliation.');
    }

    private function canFinanceApprove(): void
    {
        $admin = $this->admin();
        if (! $admin) {
            abort(403);
        }
        if ($admin->isSuperAdmin()) {
            return;
        }
        if (in_array($admin->role, ['admin'], true)) {
            return;
        }
        if ($admin->hasPermission('logistics.finance')) {
            return;
        }
        abort(403, 'You do not have permission to approve financial actions.');
    }
}
