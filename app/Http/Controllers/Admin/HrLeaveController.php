<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\HrLeave;
use App\Models\HrLeaveType;
use App\Services\Admin\AuditLogger;
use Illuminate\Http\Request;

class HrLeaveController extends Controller
{
    public function __construct(protected AuditLogger $audit) {}

    public function index(Request $request)
    {
        $status = $request->query('status');
        $query = HrLeave::with(['employee.user', 'leaveType', 'approver'])->latest('id');

        if ($status && in_array($status, HrLeave::STATUSES, true)) {
            $query->where('status', $status);
        }

        return view('admin.hr.leaves', [
            'leaves' => $query->paginate(25),
            'types' => HrLeaveType::orderBy('name')->get(),
            'employees' => Employee::with('user')->orderBy('employee_number')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'leave_type_id' => ['required', 'exists:hr_leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $start = \Carbon\Carbon::parse($data['start_date']);
        $end = \Carbon\Carbon::parse($data['end_date']);
        $days = $start->diffInDays($end) + 1;

        $leave = HrLeave::create([
            'employee_id' => $data['employee_id'],
            'leave_type_id' => $data['leave_type_id'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'days' => $days,
            'status' => 'pending',
            'reason' => $data['reason'] ?? null,
        ]);

        $this->audit->log('hr.leave.requested', $leave, [], ['days' => $days]);

        return back()->with('success', 'Leave request recorded.');
    }

    public function approve(HrLeave $leave, Request $request)
    {
        if (! $leave->isPending()) {
            return back()->withErrors(['leave' => 'Leave request is not pending.']);
        }

        $leave->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'remarks' => $request->input('remarks'),
        ]);

        $this->audit->log('hr.leave.approved', $leave, ['status' => 'pending'], ['status' => 'approved']);

        return back()->with('success', 'Leave approved.');
    }

    public function reject(HrLeave $leave, Request $request)
    {
        if (! $leave->isPending()) {
            return back()->withErrors(['leave' => 'Leave request is not pending.']);
        }

        $leave->update([
            'status' => 'rejected',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'remarks' => $request->input('remarks'),
        ]);

        $this->audit->log('hr.leave.rejected', $leave, ['status' => 'pending'], ['status' => 'rejected']);

        return back()->with('success', 'Leave rejected.');
    }
}
