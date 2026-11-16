<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\VendorPayoutMethod;
use App\Services\Vendor\VendorFinanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Yajra\DataTables\Facades\DataTables;

class FinanceController extends Controller
{
    public function __construct(protected VendorFinanceService $finance) {}

    public function index()
    {
        $vendor = Auth::guard('vendor')->user();

        $balances = $this->finance->balances($vendor);

        $recentTransactions = $this->finance->transactionsQuery($vendor)->limit(8)->get();
        $recentPayouts = $this->finance->payoutsQuery($vendor)->limit(5)->get();

        $payoutMethods = VendorPayoutMethod::where('vendor_id', $vendor->id)
            ->where('active', true)
            ->orderByDesc('is_default')
            ->get();

        $minimumPayout = $this->finance->minimumPayout($vendor);

        return view('vendor.finance.index', compact(
            'vendor', 'balances', 'recentTransactions', 'recentPayouts', 'payoutMethods', 'minimumPayout'
        ));
    }

    public function transactions()
    {
        return view('vendor.finance.transactions');
    }

    public function transactionsData()
    {
        $vendor = Auth::guard('vendor')->user();

        return DataTables::of($this->finance->transactionsQuery($vendor))
            ->editColumn('created_at', fn ($t) => $t->created_at?->format('Y-m-d H:i'))
            ->editColumn('type', fn ($t) => '<span class="badge bg-secondary">'.e(ucfirst($t->type)).'</span>')
            ->editColumn('amount', function ($t) {
                $sign = $t->amount < 0 ? '-' : '+';
                $class = $t->amount < 0 ? 'text-danger' : 'text-success';

                return '<span class="'.$class.' fw-semibold">'.$sign.number_format(abs((float) $t->amount), 2).'</span>';
            })
            ->editColumn('balance_after', fn ($t) => number_format((float) $t->balance_after, 2))
            ->addColumn('currency', fn ($t) => $t->currency)
            ->rawColumns(['type', 'amount'])
            ->make(true);
    }

    public function payouts()
    {
        $vendor = Auth::guard('vendor')->user();

        $balances = $this->finance->balances($vendor);
        $minimumPayout = $this->finance->minimumPayout($vendor);
        $payoutMethods = VendorPayoutMethod::where('vendor_id', $vendor->id)
            ->where('active', true)
            ->orderByDesc('is_default')
            ->get();

        return view('vendor.finance.payouts', compact('balances', 'minimumPayout', 'payoutMethods'));
    }

    public function requestPayout(Request $request)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payout_method_id' => 'nullable|integer',
        ]);

        $vendor = Auth::guard('vendor')->user();

        $methodId = null;
        if (! empty($data['payout_method_id'])) {
            $method = VendorPayoutMethod::where('vendor_id', $vendor->id)
                ->where('active', true)
                ->find($data['payout_method_id']);

            if (! $method) {
                return back()->with('error', 'Selected payout method is not available.');
            }
            $methodId = $method->id;
        }

        try {
            $payout = $this->finance->requestPayout($vendor, (float) $data['amount'], $methodId);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('vendor.finance.payouts')
            ->with('success', 'Payout request '.$payout->reference.' submitted for approval.');
    }

    public function statement(Request $request): StreamedResponse
    {
        $vendor = Auth::guard('vendor')->user();
        $filename = 'juvo-statement-'.$vendor->id.'-'.now()->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($vendor) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Type', 'Reference', 'Description', 'Amount', 'Currency', 'Balance after']);
            foreach ($this->finance->transactionsQuery($vendor)->get() as $t) {
                fputcsv($out, [
                    $t->created_at?->format('Y-m-d H:i'),
                    $t->type,
                    $t->reference,
                    $t->description,
                    number_format((float) $t->amount, 2, '.', ''),
                    $t->currency,
                    number_format((float) $t->balance_after, 2, '.', ''),
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
