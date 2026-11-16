<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Rider\Rider;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    public function index(Request $request)
    {
        $this->canViewFinance();

        $search = $request->string('q')->toString();

        $outstanding = Order::query()
            ->where('logistics_status', 'delivered')
            ->whereNotNull('cod_collected_at')
            ->whereNull('cod_remitted_at')
            ->with(['assignedRider', 'customer'])
            ->when($search !== '', fn ($query) => $query->where(function ($inner) use ($search) {
                $inner->where('id', $search)->orWhere('logistics_tracking_id', 'like', "%{$search}%");
            }))
            ->latest('cod_collected_at')
            ->paginate(25, ['*'], 'outstanding')
            ->withQueryString();

        $awaitingCollection = Order::query()
            ->where('logistics_status', 'delivered')
            ->whereNull('cod_collected_at')
            ->where('payment_status', '!=', 'paid')
            ->whereIn('payment_method', ['cod', 'payondelivery', 'pod', 'pay_on_delivery'])
            ->with(['assignedRider', 'customer'])
            ->latest('delivered_at')
            ->limit(50)
            ->get();

        $recentlyReconciled = Order::query()
            ->whereNotNull('cod_remitted_at')
            ->with(['assignedRider'])
            ->latest('cod_remitted_at')
            ->limit(25)
            ->get();

        $byRider = Order::query()
            ->selectRaw('assigned_driver_id, COUNT(*) as orders, SUM(cod_amount) as amount')
            ->whereNotNull('cod_collected_at')
            ->whereNull('cod_remitted_at')
            ->whereNotNull('assigned_driver_id')
            ->groupBy('assigned_driver_id')
            ->get()
            ->map(function ($row) {
                $row->rider = Rider::find($row->assigned_driver_id);

                return $row;
            })
            ->filter(fn ($row) => $row->rider !== null)
            ->sortByDesc('amount')
            ->values();

        $outstandingTotals = Order::query()
            ->where('logistics_status', 'delivered')
            ->whereNotNull('cod_collected_at')
            ->whereNull('cod_remitted_at')
            ->selectRaw('COUNT(*) as orders, COALESCE(SUM(cod_amount), 0) as amount')
            ->first();

        $totals = [
            'outstanding' => (float) $outstandingTotals->amount,
            'outstanding_count' => (int) $outstandingTotals->orders,
            'awaiting' => (float) $awaitingCollection->sum('total_amount'),
            'awaiting_count' => $awaitingCollection->count(),
            'reconciled_30d' => (float) Order::whereNotNull('cod_remitted_at')
                ->where('cod_remitted_at', '>=', now()->subDays(30))
                ->sum('cod_amount'),
        ];

        return view('logistics.finance.index', compact(
            'outstanding',
            'awaitingCollection',
            'recentlyReconciled',
            'byRider',
            'totals',
            'search',
        ));
    }

    private function canViewFinance(): void
    {
        $admin = auth('logistics_admin')->user();

        abort_unless(
            $admin && (in_array($admin->role, ['super_admin', 'admin'], true)
                || $admin->hasPermission('logistics.finance')),
            403,
            'You do not have permission to view cash reconciliation.',
        );
    }

    public function payouts()
    {
        $admin = auth('logistics_admin')->user();

        abort_unless(
            $admin && ($admin->isSuperAdmin()
                || $admin->hasRole('admin')
                || $admin->hasPermission('logistics.payouts')),
            403,
            'You do not have permission to view payouts and settlements.',
        );

        return view('logistics.payouts.index');
    }
}
