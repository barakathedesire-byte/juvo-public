<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\HrAttendance;
use App\Services\Admin\AuditLogger;
use Illuminate\Http\Request;

class HrAttendanceController extends Controller
{
    public function __construct(protected AuditLogger $audit) {}

    public function index(Request $request)
    {
        $date = $request->query('date', now()->toDateString());

        $records = HrAttendance::with(['employee.user', 'recorder'])
            ->whereDate('attendance_date', $date)
            ->get()
            ->keyBy('employee_id');

        return view('admin.hr.attendance', [
            'date' => $date,
            'employees' => Employee::with('user')->orderBy('employee_number')->get(),
            'records' => $records,
            'statuses' => HrAttendance::STATUSES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'attendance_date' => ['required', 'date'],
            'status' => ['required', 'in:present,absent,late,half_day'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        HrAttendance::updateOrCreate(
            ['employee_id' => $data['employee_id'], 'attendance_date' => $data['attendance_date']],
            [
                'status' => $data['status'],
                'notes' => $data['notes'] ?? null,
                'recorded_by' => auth()->id(),
                'source' => 'manual',
            ]
        );

        $this->audit->log('hr.attendance.recorded', null, [], [
            'employee_id' => $data['employee_id'],
            'date' => $data['attendance_date'],
            'status' => $data['status'],
        ]);

        return back()->with('success', 'Attendance recorded.');
    }
}
