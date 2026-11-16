<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\Position;
use App\Models\StaffDepartment;
use App\Models\User;
use App\Services\Admin\AuditLogger;
use App\Services\Hr\EmployeeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HrController extends Controller
{
    public function __construct(protected EmployeeService $employees, protected AuditLogger $audit) {}

    public function index(Request $request)
    {
        $departments = StaffDepartment::orderBy('name')->withCount('users')->get();
        $positions = Position::with('department')->orderBy('sort_order')->orderBy('name')->get();
        $employees = Employee::with(['user', 'department', 'position', 'manager'])
            ->latest('id')
            ->paginate(25);

        $stats = [
            'departments' => $departments->count(),
            'positions' => $positions->count(),
            'employees_total' => Employee::count(),
            'employees_active' => Employee::where('status', Employee::STATUS_ACTIVE)->count(),
        ];

        return view('admin.hr.index', compact('stats', 'departments', 'positions', 'employees'));
    }

    public function departments()
    {
        $departments = StaffDepartment::orderBy('name')->withCount(['users', 'positions'])->get();

        return view('admin.hr.departments', compact('departments'));
    }

    public function createDepartment()
    {
        return view('admin.hr.create_department');
    }

    public function storeDepartment(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        StaffDepartment::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return redirect()->route('admin.hr.departments')->with('success', 'Department created.');
    }

    public function positions()
    {
        $positions = Position::with('department')->orderBy('sort_order')->orderBy('name')->get();
        $departments = StaffDepartment::active()->orderBy('name')->get();

        return view('admin.hr.positions', compact('positions', 'departments'));
    }

    public function storePosition(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'department_id' => ['nullable', 'exists:staff_departments,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        Position::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']).'-'.uniqid(),
            'department_id' => $data['department_id'] ?? null,
            'description' => $data['description'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return redirect()->route('admin.hr.positions')->with('success', 'Position created.');
    }

    public function employees()
    {
        $employees = Employee::with(['user', 'department', 'position', 'manager'])
            ->orderBy('employee_number')
            ->paginate(30);

        return view('admin.hr.employees', compact('employees'));
    }

    public function createEmployee()
    {
        $users = User::orderBy('name')->get();
        $departments = StaffDepartment::active()->orderBy('name')->get();
        $positions = Position::active()->with('department')->orderBy('name')->get();
        $managers = Employee::with('user')->orderBy('employee_number')->get();

        return view('admin.hr.create_employee', compact('users', 'departments', 'positions', 'managers'));
    }

    public function storeEmployee(Request $request)
    {
        $data = $request->validate([
            'employee_number' => ['required', 'string', 'max:50', 'unique:employees,employee_number'],
            'user_id' => ['nullable', 'exists:users,id', 'unique:employees,user_id'],
            'department_id' => ['nullable', 'exists:staff_departments,id'],
            'position_id' => ['nullable', 'exists:positions,id'],
            'manager_id' => ['nullable', 'exists:employees,id'],
            'employment_type' => ['required', 'in:full_time,part_time,contract,intern'],
            'hire_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'status' => ['required', 'in:active,on_leave,suspended,terminated'],
            'work_email' => ['nullable', 'email', 'max:255'],
            'work_phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        // Prevent self-management / reporting cycles (enforced by the service).
        try {
            $employee = $this->employees->create(array_merge($data, [
                'created_by' => Auth::id(),
            ]));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $this->audit->log('hr.employee.created', $employee, [], [
            'employee_number' => $employee->employee_number,
            'department_id' => $employee->department_id,
        ]);

        return redirect()->route('admin.hr.employees')->with('success', 'Employee record created.');
    }

    public function editEmployee(Employee $employee)
    {
        return view('admin.hr.edit_employee', [
            'employee' => $employee,
            'users' => User::orderBy('name')->get(),
            'departments' => StaffDepartment::active()->orderBy('name')->get(),
            'positions' => Position::active()->with('department')->orderBy('name')->get(),
            'managers' => Employee::with('user')->where('id', '!=', $employee->id)->orderBy('employee_number')->get(),
        ]);
    }

    public function updateEmployee(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'employee_number' => ['required', 'string', 'max:50', 'unique:employees,employee_number,'.$employee->id],
            'user_id' => ['nullable', 'exists:users,id', 'unique:employees,user_id,'.$employee->id],
            'department_id' => ['nullable', 'exists:staff_departments,id'],
            'position_id' => ['nullable', 'exists:positions,id'],
            'manager_id' => ['nullable', 'exists:employees,id'],
            'employment_type' => ['required', 'in:full_time,part_time,contract,intern'],
            'hire_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'status' => ['required', 'in:active,on_leave,suspended,terminated'],
            'work_email' => ['nullable', 'email', 'max:255'],
            'work_phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $before = ['status' => $employee->status, 'manager_id' => $employee->manager_id];

        try {
            $this->employees->update($employee, $data);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $this->audit->log('hr.employee.updated', $employee, $before, [
            'status' => $employee->status,
            'manager_id' => $employee->manager_id,
        ]);

        return redirect()->route('admin.hr.employees')->with('success', 'Employee record updated.');
    }

    public function documents()
    {
        $documents = EmployeeDocument::with(['employee.user', 'uploader'])
            ->latest('id')
            ->paginate(30);

        return view('admin.hr.documents', compact('documents'));
    }

    public function showDocument(EmployeeDocument $document)
    {
        abort_unless($document->file_path, 404);

        // Serve only local disk paths; never expose confidential via public URLs.
        return response()->file(Storage::disk($document->disk ?: 'local')->path($document->file_path));
    }
}
