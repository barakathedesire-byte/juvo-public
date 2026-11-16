<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Rider\Rider;
use App\Models\Rider\RiderLedgerEntry;
use App\Models\Rider\RiderPayout;
use App\Services\Logistics\RiderPayoutService;
use Illuminate\Http\Request;

class PayoutFinanceController extends Controller
{
    public function __construct(private readonly RiderPayoutService $payouts) {}

    public function index(Request $request)
    {
        $this->canViewPayouts();

        $search = $request->string('q')->toString();
        $status = $request->string('status')->toString();
        $riderId = $request->integer('rider_id');

        $riderQuery = Rider::query()
            ->where('is_active', true)
            ->with(['payouts']);

        if ($search !== '') {
            $riderQuery->where(function ($inner) use ($search) {
                $inner->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $riders = $riderQuery->orderBy('first_name')->paginate(25, ['*'], 'riders')->withQueryString();

        $pending = RiderPayout::with(['rider'])
            ->whereIn('status', ['pending_approval', 'approved', 'processing'])
            ->latest()
            ->limit(30)
            ->get();

        $recent = RiderPayout::with(['rider'])
            ->whereIn('status', ['completed', 'failed', 'rejected', 'reversed'])
            ->latest()
            ->limit(25)
            ->get();

        $creditActive = (float) RiderLedgerEntry::where('direction', 'credit')->where('status', 'available')->sum('amount');
        $debitActive = (float) RiderLedgerEntry::where('direction', 'debit')->whereIn('status', ['available', 'pending', 'paid'])->sum('amount');

        $totals = [
            'earnings' => (float) RiderLedgerEntry::where('direction', 'credit')
                ->whereIn('type', ['delivery_earning', 'bonus', 'adjustment'])
                ->whereNotIn('status', ['reversed', 'rejected'])
                ->sum('amount'),
            'pending_earnings' => (float) \App\Models\Order::whereIn('logistics_status', ['assigned', 'accepted', 'picked_up', 'in_transit'])->sum('delivery_fee'),
            'available' => max(0.0, $creditActive - $debitActive),
            'pending' => (float) RiderPayout::where('status', 'pending_approval')->sum('amount'),
            'approved' => (float) RiderPayout::where('status', 'approved')->sum('amount'),
            'completed' => (float) RiderPayout::where('status', 'completed')->sum('amount'),
            'failed' => (float) RiderPayout::whereIn('status', ['failed', 'rejected', 'reversed'])->sum('amount'),
        ];

        $stats = $riders->getCollection()->map(function (Rider $rider) {
            return [
                'rider' => $rider,
                'balance' => $this->payouts->overview($rider),
            ];
        });

        return view('logistics.payouts.index', compact(
            'riders', 'pending', 'recent', 'totals', 'stats', 'search', 'status', 'riderId'
        ));
    }

    public function show(RiderPayout $payout)
    {
        $this->canViewPayouts();

        $payout->load(['rider', 'ledgerEntry']);
        $audit = \App\Models\AuditLog::where('auditable_type', $payout->getMorphClass())
            ->where('auditable_id', $payout->id)
            ->latest('id')
            ->limit(50)
            ->get();

        $overview = $this->payouts->overview($payout->rider);

        return view('logistics.payouts.show', compact('payout', 'audit', 'overview'));
    }

    public function riderStatement(Rider $rider)
    {
        $this->canViewPayouts();

        $overview = $this->payouts->overview($rider);

        $ledger = RiderLedgerEntry::where('rider_id', $rider->id)
            ->with(['order', 'payout'])
            ->latest('occurred_at')
            ->latest('id')
            ->paginate(40)
            ->withQueryString();

        $payouts = RiderPayout::where('rider_id', $rider->id)
            ->latest()
            ->paginate(20, ['*'], 'payouts')
            ->withQueryString();

        return view('logistics.payouts.rider', compact('rider', 'overview', 'ledger', 'payouts'));
    }

    public function create(Request $request, Rider $rider)
    {
        $this->canApprovePayouts();

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|in:bank,mobile_money',
            'currency' => 'nullable|in:TZS,USD,EUR',
            'account_name' => 'nullable|string|max:120',
            'account_number' => 'nullable|string|max:64',
            'bank_name' => 'nullable|string|max:80',
            'provider' => 'nullable|string|max:64',
            'notes' => 'nullable|string|max:1000',
        ]);

        $admin = $this->admin();

        try {
            $payout = $this->payouts->createPayout($rider, $data, $admin);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return redirect()->back()->withErrors($exception->errors())->withInput();
        }

        return redirect()->route('logistics.payouts.rider', $rider)->with('success', 'Payout created. Reference: '.$payout->reference);
    }

    public function approve(RiderPayout $payout)
    {
        $this->canApprovePayouts();

        $admin = $this->admin();

        try {
            $this->payouts->approve($payout, $admin);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return redirect()->back()->withErrors($exception->errors());
        }

        return redirect()->back()->with('success', 'Payout approved.');
    }

    public function complete(Request $request, RiderPayout $payout)
    {
        $this->canCompletePayouts();

        $data = $request->validate([
            'provider_reference' => 'required|string|max:120',
            'provider' => 'nullable|string|max:64',
            'confirmed' => 'required|accepted',
        ]);

        $admin = $this->admin();

        try {
            $this->payouts->complete($payout, $data, $admin);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return redirect()->back()->withErrors($exception->errors());
        }

        return redirect()->back()->with('success', 'Payout marked as completed.');
    }

    public function reject(Request $request, RiderPayout $payout)
    {
        $this->canApprovePayouts();

        $data = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $admin = $this->admin();

        try {
            $this->payouts->reject($payout, $data['reason'], $admin);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return redirect()->back()->withErrors($exception->errors());
        }

        return redirect()->back()->with('success', 'Payout rejected.');
    }

    public function fail(Request $request, RiderPayout $payout)
    {
        $this->canCompletePayouts();

        $data = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $admin = $this->admin();

        try {
            $this->payouts->fail($payout, $data['reason'], $admin);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return redirect()->back()->withErrors($exception->errors());
        }

        return redirect()->back()->with('success', 'Payout marked as failed.');
    }

    public function reverse(Request $request, RiderPayout $payout)
    {
        $this->canCompletePayouts();

        $data = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $admin = $this->admin();

        if (! $admin->isSuperAdmin()) {
            abort(403, 'Only super administrators can reverse completed payouts.');
        }

        try {
            $this->payouts->reverse($payout, $data['reason'], $admin);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return redirect()->back()->withErrors($exception->errors());
        }

        return redirect()->back()->with('success', 'Payout reversed.');
    }

    public function export(Request $request)
    {
        $this->canViewPayouts();

        $payouts = RiderPayout::with(['rider'])->latest()->get();

        $headers = ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="rider_payouts.csv"'];

        $callback = function () use ($payouts) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['reference', 'rider', 'amount', 'currency', 'method', 'status', 'provider_reference', 'initiated_by', 'approved_by', 'completed_at']);
            foreach ($payouts as $payout) {
                fputcsv($handle, [
                    $payout->reference,
                    $payout->rider ? $payout->rider->full_name : '',
                    $payout->amount,
                    $payout->currency,
                    $payout->method,
                    $payout->status,
                    $payout->provider_reference,
                    $payout->initiated_by_name,
                    $payout->approved_by_name,
                    optional($payout->completed_at)->toDateTimeString(),
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

    private function canViewPayouts(): void
    {
        $admin = $this->admin();
        abort_unless($admin && ($admin->isSuperAdmin() || in_array($admin->role, ['admin'], true) || $admin->hasPermission('logistics.payouts')), 403, 'You do not have permission to view payouts and settlements.');
    }

    private function canApprovePayouts(): void
    {
        $admin = $this->admin();
        abort_unless($admin && ($admin->isSuperAdmin() || in_array($admin->role, ['admin'], true) || $admin->hasPermission('logistics.payouts')), 403, 'You do not have permission to approve payouts.');
    }

    private function canCompletePayouts(): void
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
        if ($admin->hasPermission('logistics.payouts')) {
            return;
        }
        abort(403, 'You do not have permission to complete payouts.');
    }
}
