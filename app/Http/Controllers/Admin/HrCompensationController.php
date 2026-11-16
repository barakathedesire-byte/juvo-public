<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\HrCompensation;
use App\Services\Admin\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HrCompensationController extends Controller
{
    public function __construct(protected AuditLogger $audit) {}

    public function index()
    {
        $compensations = HrCompensation::with(['employee.user', 'creator'])
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->paginate(30);

        $employees = Employee::with('user')->orderBy('employee_number')->get();

        return view('admin.hr.compensation', [
            'compensations' => $compensations,
            'employees' => $employees,
            'frequencies' => HrCompensation::FREQUENCIES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:8'],
            'pay_frequency' => ['required', 'in:'.implode(',', HrCompensation::FREQUENCIES)],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $compensation = HrCompensation::create([
            'employee_id' => $data['employee_id'],
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'TZS',
            'pay_frequency' => $data['pay_frequency'],
            'effective_from' => $data['effective_from'],
            'effective_to' => $data['effective_to'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => Auth::id(),
        ]);

        $this->audit->log('hr.compensation.created', $compensation, [], [
            'employee_id' => $compensation->employee_id,
            'pay_frequency' => $compensation->pay_frequency,
            'effective_from' => optional($compensation->effective_from)->toDateString(),
        ]);

        return redirect()->route('admin.hr.compensation.index')
            ->with('success', 'Compensation record added.');
    }
}
