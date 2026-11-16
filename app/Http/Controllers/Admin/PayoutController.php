<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\VendorCommission;
use App\Models\VendorPayout;
use App\Services\Access\AuthorizationService;
use App\Services\Admin\ApprovalService;
use App\Services\Vendor\VendorFinanceService;
use App\Support\Access\AccessScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayoutController extends Controller
{
    public function __construct(
        protected VendorFinanceService $finance,
        protected ApprovalService $approvals,
        protected AuthorizationService $auth,
    ) {}

    public function index()
    {
        $scope = $this->auth->scope(auth()->user());

        $vendors = Vendor::query()
            ->when(
                $scope->type === AccessScope::VENDORS,
                fn ($q) => $q->whereIn('id', $scope->ids ?: [-1])
            )
            ->withCount('commissions')
            ->orderBy('name')
            ->get()
            ->map(function (Vendor $vendor) {
                $vendor->gross_sales = (float) VendorCommission::where('vendor_id', $vendor->id)
                    ->where('status', '!=', 'reversed')->sum('gross_amount');
                $vendor->commission_owed = (float) VendorCommission::where('vendor_id', $vendor->id)
                    ->where('status', '!=', 'reversed')->sum('commission_amount');
                $vendor->payable = $this->finance->balances($vendor)['available'];

                $vendor->paid_out = (float) VendorPayout::where('vendor_id', $vendor->id)
                    ->where('status', 'paid')->sum('amount');

                return $vendor;
            });

        $payouts = VendorPayout::with('vendor')->orderByDesc('id')->paginate(25);

        $totals = [
            'gross_sales' => $vendors->sum('gross_sales'),
            'commission' => $vendors->sum('commission_owed'),
            'payable' => $vendors->sum('payable'),
            'paid_out' => $vendors->sum('paid_out'),
        ];

        return view('admin.payouts.index', compact('vendors', 'payouts', 'totals'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'vendor_id' => 'required|exists:vendors,id',
            'amount' => 'required|numeric|min:0.01',
            'method' => 'nullable|string|max:60',
            'notes' => 'nullable|string|max:500',
        ]);

        $vendor = Vendor::findOrFail($data['vendor_id']);

        // Central authorization: permission + vendor record scope.
        $this->auth->authorize($request->user(), ['payouts.create', 'manage-finances'], [
            'vendor_id' => $vendor->id,
            'write' => true,
        ]);

        $amount = round((float) $data['amount'], 2);
        $balances = $this->finance->balances($vendor);

        if ($amount > $balances['withdrawable']) {
            return back()->with('error', 'Payout amount exceeds the vendor\'s withdrawable balance of '.number_format($balances['withdrawable'], 2).'.');
        }

        $payout = VendorPayout::create([
            'vendor_id' => $vendor->id,
            'reference' => 'PAY-'.strtoupper(uniqid()),
            'amount' => $amount,
            'currency' => getWebConfig('default_currency', 'TZS'),
            'method' => $data['method'] ?? 'manual',
            'status' => 'requested',
            'requested_at' => now(),
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'Payout '.$payout->reference.' created.');
    }

    public function pay(Request $request, VendorPayout $payout)
    {
        $this->auth->authorize($request->user(), ['payouts.release', 'manage-finances'], [
            'vendor_id' => $payout->vendor_id,
            'write' => true,
        ]);

        $amount = (float) $payout->amount;

        // Releasing vendor funds beyond the policy threshold requires a second
        // approver before the payout can be settled (maker-checker).
        $approved = null;

        if ($this->approvals->requiresApproval('finance.payout.release', $amount)) {
            $approved = $this->approvals->approvedFor('finance.payout.release', $payout, $amount);

            if (! $approved) {
                $pending = $this->approvals->pendingFor('finance.payout.release', $payout, $amount)
                    ?? $this->approvals->submit(
                        auth()->user(),
                        'finance.payout.release',
                        $payout,
                        ['amount' => $amount, 'reference' => $payout->reference],
                        'Payout release '.$payout->reference,
                        $amount,
                    );

                return back()->with('success', 'This payout exceeds the approval threshold and is awaiting approval ('.$pending->reference.').');
            }
        }

        $settled = DB::transaction(function () use ($payout, $request) {
            $locked = VendorPayout::whereKey($payout->getKey())->lockForUpdate()->first();

            if (! $locked || in_array($locked->status, ['paid', 'rejected'], true)) {
                return false;
            }

            $locked->update([
                'status' => 'paid',
                'approved_at' => $locked->approved_at ?? now(),
                'processed_at' => now(),
                'paid_at' => now(),
                'approved_by' => auth()->id(),
                'processed_by' => auth()->id(),
                'provider_reference' => $request->input('provider_reference'),
            ]);

            if ($locked->vendor) {
                $this->finance->settleForPayout($locked->refresh());
            }

            return true;
        });

        if (! $settled) {
            return back()->with('error', 'This payout is already finalised.');
        }

        if ($approved) {
            $this->approvals->markExecuted($approved, auth()->user());
        }

        return back()->with('success', 'Payout marked as paid.');
    }

    public function reject(Request $request, VendorPayout $payout)
    {
        $this->auth->authorize($request->user(), ['payouts.release', 'manage-finances'], [
            'vendor_id' => $payout->vendor_id,
            'write' => true,
        ]);

        if ($payout->status === 'paid') {
            return back()->with('error', 'A paid payout cannot be rejected.');
        }

        $payout->update([
            'status' => 'rejected',
            'rejection_reason' => $request->input('reason', 'Rejected by admin.'),
        ]);

        return back()->with('success', 'Payout rejected.');
    }
}
