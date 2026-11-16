<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\VendorVerificationDocument;
use App\Services\Access\AuthorizationService;
use App\Services\Admin\ApprovalService;
use App\Services\Vendor\VendorFinanceService;
use App\Support\Access\AccessScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Yajra\DataTables\Facades\DataTables;

class VendorController extends Controller
{
    public function __construct(
        protected VendorFinanceService $finance,
        protected ApprovalService $approvals,
        protected AuthorizationService $auth,
    ) {}

    public function index()
    {
        return view('admin.vendors.index');
    }

    public function getVendorData()
    {
        $vendors = Vendor::select(['id', 'name', 'shop_name', 'email', 'phone', 'status', 'is_approved']);

        $scope = $this->auth->scope(Auth::user());
        if ($scope->type === AccessScope::VENDORS) {
            $vendors->whereIn('id', $scope->ids ?: [-1]);
        }

        return DataTables::of($vendors)
            ->addColumn('action', function ($vendor) {
                $view = '<a href="'.route('admin.vendors.show', $vendor->id).'" class="border border-primary rounded-3 d-inline-block me-1 px-2" title="Review"><i class="bi bi-eye-fill text-primary"></i></a>';

                return $view.'<span class="border border-danger dt-trash rounded-3 d-inline-block" onclick="deleteVendor('.$vendor->id.')"><i class="bi bi-trash-fill text-danger"></i></span>';
            })
            ->rawColumns(['action'])
            ->make(true);
    }

    public function show($id)
    {
        $vendor = Vendor::findOrFail($id);

        $this->authorizeVendor($vendor);

        $stats = [
            'products' => \App\Models\Product::where('vendor_id', $vendor->id)->count(),
            'orders' => \App\Models\Order::where('vendor_id', $vendor->id)->count(),
            'completed_orders' => \App\Models\Order::where('vendor_id', $vendor->id)->where('status', 'completed')->count(),
            'revenue' => (float) \App\Models\Order::where('vendor_id', $vendor->id)->where('status', 'completed')->sum('total_amount'),
        ];

        $balances = $this->finance->balances($vendor);
        $adjustments = $this->finance->transactionsQuery($vendor)->limit(10)->get();

        return view('admin.vendors.show', compact('vendor', 'stats', 'balances', 'adjustments'));
    }

    public function reject(Request $request, $id)
    {
        $vendor = Vendor::findOrFail($id);
        $this->authorizeVendor($vendor, true);
        $vendor->is_approved = false;
        $vendor->status = 'rejected';
        $vendor->save();

        return redirect()->route('admin.vendors.show', $vendor->id)
            ->with('success', 'Vendor application rejected.');
    }

    public function approve($id)
    {
        $vendor = Vendor::findOrFail($id);
        $this->authorizeVendor($vendor, true);
        $vendor->is_approved = true;
        $vendor->status = 'active';
        $vendor->save();

        return response()->json([
            'success' => true,
            'message' => 'Vendor approved and activated.',
        ]);
    }

    public function verificationQueue()
    {
        $vendors = Vendor::withCount('verificationDocuments')
            ->where(function ($q) {
                $q->where('verification_status', 'pending')
                    ->orWhereNull('verification_status');
            })
            ->where('is_approved', false)
            ->latest()
            ->paginate(20);

        return view('admin.vendors.verification', compact('vendors'));
    }

    public function approveVerification($id)
    {
        $vendor = Vendor::findOrFail($id);

        $vendor->update([
            'verification_status' => 'verified',
            'verified_at' => now(),
            'verified_by' => Auth::id(),
            'is_approved' => true,
            'status' => 'active',
            'suspended_at' => null,
            'suspension_reason' => null,
        ]);

        $this->notifyVendor($vendor, 'verified');

        return back()->with('success', 'Vendor verified and store activated.');
    }

    /**
     * Send the vendor an email + in-app notification. Never block the admin
     * action if delivery fails.
     */
    protected function notifyVendor(Vendor $vendor, string $event, ?string $note = null): void
    {
        if (empty($vendor->email)) {
            return;
        }

        try {
            $vendor->notify(new \App\Notifications\VendorVerificationNotification($vendor, $event, $note));
        } catch (\Throwable $e) {
            \Log::warning('Vendor notification failed for vendor #'.$vendor->id.': '.$e->getMessage());
        }
    }

    public function rejectVerification(Request $request, $id)
    {
        $data = $request->validate(['reason' => 'required|string|max:500']);
        $vendor = Vendor::findOrFail($id);

        $vendor->update([
            'verification_status' => 'rejected',
            'is_approved' => false,
            'status' => 'inactive',
        ]);

        $vendor->verificationDocuments()
            ->where('status', '!=', 'approved')
            ->update(['rejection_reason' => $data['reason']]);

        $this->notifyVendor($vendor, 'rejected', $data['reason']);

        return back()->with('success', 'Vendor verification rejected.');
    }

    public function downloadDocument($id)
    {
        $document = VendorVerificationDocument::findOrFail($id);
        $vendor = $document->vendor;

        abort_unless($vendor, 404);
        $this->authorizeVendor($vendor);

        $disk = Storage::disk($document->disk ?: 'local');

        abort_unless($disk->exists($document->file_path), 404);

        return $disk->download($document->file_path);
    }

    public function approveDocument($id)
    {
        $document = VendorVerificationDocument::findOrFail($id);

        $document->update([
            'status' => 'approved',
            'rejection_reason' => null,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Document approved.');
    }

    public function rejectDocument(Request $request, $id)
    {
        $data = $request->validate(['reason' => 'required|string|max:500']);
        $document = VendorVerificationDocument::findOrFail($id);

        $document->update([
            'status' => 'rejected',
            'rejection_reason' => $data['reason'],
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Document rejected.');
    }

    public function suspend(Request $request, $id)
    {
        $data = $request->validate(['reason' => 'required|string|max:500']);
        $vendor = Vendor::findOrFail($id);

        $this->authorizeVendor($vendor, true);

        $vendor->update([
            'status' => 'suspended',
            'suspended_at' => now(),
            'suspension_reason' => $data['reason'],
        ]);

        $this->notifyVendor($vendor, 'suspended', $data['reason']);

        return back()->with('success', 'Vendor suspended.');
    }

    public function unsuspend($id)
    {
        $vendor = Vendor::findOrFail($id);
        $this->authorizeVendor($vendor, true);

        $vendor->update([
            'status' => 'active',
            'suspended_at' => null,
            'suspension_reason' => null,
        ]);

        $this->notifyVendor($vendor, 'reinstated');

        return back()->with('success', 'Vendor reinstated.');
    }

    public function restrict(Request $request, $id)
    {
        $data = $request->validate(['reason' => 'nullable|string|max:500']);
        $vendor = Vendor::findOrFail($id);

        $this->authorizeVendor($vendor, true);

        $vendor->update([
            'status' => 'restricted',
            'suspension_reason' => $data['reason'] ?? 'Restricted by marketplace.',
        ]);

        $this->notifyVendor($vendor, 'restricted', $data['reason'] ?? null);

        return back()->with('success', 'Vendor restricted.');
    }

    public function unrestrict($id)
    {
        $vendor = Vendor::findOrFail($id);
        $this->authorizeVendor($vendor, true);

        $vendor->update([
            'status' => 'active',
            'suspended_at' => null,
            'suspension_reason' => null,
        ]);

        $this->notifyVendor($vendor, 'reinstated');

        return back()->with('success', 'Vendor restrictions lifted.');
    }

    public function create()
    {
        return view('admin.vendors.create');
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:vendors,email'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->symbols(),
            ],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9\s\-]+$/'],
            'status' => ['required', 'in:active,inactive,pending,restricted,suspended,rejected,banned'],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'minimum_payout' => ['nullable', 'numeric', 'min:0'],
        ]);

        Vendor::create([
            'name' => trim($validatedData['name']),
            'email' => strtolower(trim($validatedData['email'])),
            'password' => Hash::make($validatedData['password']),
            'phone' => $validatedData['phone'] ?? null,
            'status' => $validatedData['status'],
            'commission_rate' => $validatedData['commission_rate'] ?? null,
            'minimum_payout' => $validatedData['minimum_payout'] ?? null,
        ]);

        return redirect()->route('admin.vendors.index')
            ->with('success', 'Vendor registered successfully!');
    }

    public function updateCommission(Request $request, $id)
    {
        $vendor = Vendor::findOrFail($id);
        $this->authorizeVendor($vendor, true);

        $data = $request->validate([
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'minimum_payout' => ['nullable', 'numeric', 'min:0'],
        ]);

        $vendor->update([
            'commission_rate' => $data['commission_rate'] ?? null,
            'minimum_payout' => $data['minimum_payout'] ?? null,
        ]);

        return back()->with('success', 'Commission settings updated.');
    }

    public function adjustBalance(Request $request, $id)
    {
        $vendor = Vendor::where('id', $id)->firstOrFail();
        $this->authorizeVendor($vendor, true);

        $data = $request->validate([
            'amount' => 'required|numeric',
            'reason' => 'required|string|max:255',
        ]);

        if ((float) $data['amount'] === 0.0) {
            return back()->withErrors(['amount' => 'Enter a non-zero amount.']);
        }

        $amount = (float) $data['amount'];

        // Sensitive adjustments beyond the policy threshold require a second
        // approver before they can be posted (maker-checker).
        if ($this->approvals->requiresApproval('finance.adjustment.create', abs($amount))) {
            $approved = $this->approvals->approvedFor('finance.adjustment.create', $vendor, abs($amount));

            if (! $approved) {
                $pending = $this->approvals->pendingFor('finance.adjustment.create', $vendor, abs($amount))
                    ?? $this->approvals->submit(
                        auth()->user(),
                        'finance.adjustment.create',
                        $vendor,
                        ['amount' => $amount, 'reason' => $data['reason']],
                        $data['reason'],
                        abs($amount),
                    );

                return back()->with('success', 'This adjustment exceeds the approval threshold and is awaiting approval ('.$pending->reference.').');
            }
        }

        try {
            $transaction = $this->finance->adjust(
                $vendor,
                $amount,
                $data['reason'],
                auth()->id()
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        if (isset($approved) && $approved) {
            $this->approvals->markExecuted($approved, auth()->user());
        }

        return back()->with('success', 'Balance adjusted: '.number_format((float) $transaction->amount, 2).' ('.$transaction->reference.').');
    }

    public function destroy($id)
    {
        $vendor = Vendor::findOrFail($id);
        $this->authorizeVendor($vendor, true);
        $vendor->delete();

        return response()->json([
            'success' => true,
            'message' => __('cms.vendors.success_delete'),
        ]);
    }

    /**
     * Enforce the vendor management permission plus vendor record scope via the
     * central engine, so vendor-restricted staff cannot touch out-of-scope ids.
     */
    protected function authorizeVendor(?Vendor $vendor, bool $write = false): void
    {
        $this->auth->authorize(Auth::user(), 'manage-vendors', [
            'vendor_id' => $vendor?->id,
            'write' => $write,
        ]);
    }
}
