<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\VendorPayout;
use App\Models\VendorTransaction;
use App\Services\Admin\FinanceReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Yajra\DataTables\Facades\DataTables;

class FinanceController extends Controller
{
    public function __construct(protected FinanceReportService $reports) {}

    public function index(Request $request)
    {
        [$from, $to] = $this->range($request);

        $reconciliation = $this->reports->reconciliation();

        return view('admin.finance.index', [
            'kpis' => $this->reports->kpis($from, $to),
            'from' => $from,
            'to' => $to,
            'topVendors' => $reconciliation
                ->sortByDesc('withdrawable')
                ->take(10)
                ->values(),
            'recentPayouts' => VendorPayout::with('vendor')->latest('id')->limit(8)->get(),
            'recentTransactions' => VendorTransaction::with('vendor')->latest('id')->limit(8)->get(),
        ]);
    }

    public function ledger(Request $request)
    {
        return view('admin.finance.ledger', [
            'vendors' => Vendor::orderBy('name')->get(['id', 'name']),
            'types' => VendorTransaction::query()->select('type')->distinct()->orderBy('type')->pluck('type'),
        ]);
    }

    public function ledgerData(Request $request)
    {
        $query = VendorTransaction::with('vendor')->select('vendor_transactions.*');

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        if ($vendorId = $request->query('vendor_id')) {
            $query->where('vendor_id', $vendorId);
        }

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return DataTables::of($query)
            ->addColumn('vendor_name', fn ($t) => $t->vendor?->name ?? '—')
            ->editColumn('amount', fn ($t) => number_format((float) $t->amount, 2))
            ->editColumn('balance_after', fn ($t) => number_format((float) $t->balance_after, 2))
            ->editColumn('created_at', fn ($t) => $t->created_at?->format('Y-m-d H:i'))
            ->make(true);
    }

    public function reconciliation(Request $request)
    {
        $rows = $this->reports->reconciliation($request->query('q'));

        return view('admin.finance.reconciliation', [
            'rows' => $rows,
            'totals' => [
                'pending' => round($rows->sum('pending'), 2),
                'available' => round($rows->sum('available'), 2),
                'reserved' => round($rows->sum('reserved'), 2),
                'withdrawable' => round($rows->sum('withdrawable'), 2),
                'paid' => round($rows->sum('paid'), 2),
            ],
            'search' => (string) $request->query('q', ''),
        ]);
    }

    /**
     * @return array{0:?Carbon,1:?Carbon}
     */
    protected function range(Request $request): array
    {
        if (! $request->filled('from') && ! $request->filled('to')) {
            return [null, null];
        }

        $from = $request->filled('from') ? Carbon::parse($request->query('from'))->startOfDay() : Carbon::parse('2000-01-01');
        $to = $request->filled('to') ? Carbon::parse($request->query('to'))->endOfDay() : Carbon::now()->endOfDay();

        return [$from, $to];
    }
}
