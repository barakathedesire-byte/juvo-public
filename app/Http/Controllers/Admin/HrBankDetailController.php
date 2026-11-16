<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeBankDetail;
use App\Services\Admin\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Employee bank details. Account numbers are encrypted at rest and only ever
 * rendered masked; the raw value is never logged, exported or echoed back. All
 * reads require hr.view_bank_details and all writes hr.manage_bank_details.
 */
class HrBankDetailController extends Controller
{
    public function __construct(protected AuditLogger $audit) {}

    public function index()
    {
        $bankDetails = EmployeeBankDetail::with(['employee.user', 'creator'])
            ->orderByDesc('is_primary')
            ->orderByDesc('id')
            ->paginate(30);

        $employees = Employee::with('user')->orderBy('employee_number')->get();

        return view('admin.hr.bank_details', [
            'bankDetails' => $bankDetails,
            'employees' => $employees,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'bank_name' => ['required', 'string', 'max:255'],
            'account_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:64'],
            'branch' => ['nullable', 'string', 'max:255'],
            'swift_code' => ['nullable', 'string', 'max:32'],
            'currency' => ['nullable', 'string', 'max:8'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        $isPrimary = $request->boolean('is_primary');

        $detail = DB::transaction(function () use ($data, $isPrimary) {
            if ($isPrimary) {
                EmployeeBankDetail::where('employee_id', $data['employee_id'])
                    ->update(['is_primary' => false]);
            }

            return EmployeeBankDetail::create([
                'employee_id' => $data['employee_id'],
                'bank_name' => $data['bank_name'],
                'account_name' => $data['account_name'],
                'account_number' => $data['account_number'],
                'branch' => $data['branch'] ?? null,
                'swift_code' => $data['swift_code'] ?? null,
                'currency' => $data['currency'] ?? null,
                'is_primary' => $isPrimary,
                'created_by' => Auth::id(),
            ]);
        });

        // Audit only non-sensitive descriptors; never the account number.
        $this->audit->log('hr.bank_detail.created', $detail, [], [
            'employee_id' => $detail->employee_id,
            'bank_name' => $detail->bank_name,
            'account_last4' => $detail->lastFour(),
            'is_primary' => $detail->is_primary,
        ]);

        return redirect()->route('admin.hr.bank.index')
            ->with('success', 'Bank details saved.');
    }

    public function destroy(EmployeeBankDetail $bankDetail)
    {
        $snapshot = [
            'employee_id' => $bankDetail->employee_id,
            'bank_name' => $bankDetail->bank_name,
            'account_last4' => $bankDetail->lastFour(),
        ];

        $bankDetail->delete();

        $this->audit->log('hr.bank_detail.deleted', null, $snapshot, [], 'bank details removed');

        return redirect()->route('admin.hr.bank.index')
            ->with('success', 'Bank details removed.');
    }
}
