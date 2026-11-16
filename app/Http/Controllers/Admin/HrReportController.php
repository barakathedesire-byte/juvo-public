<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\HrAttendance;
use App\Models\HrCandidate;
use App\Models\HrJobPost;
use App\Models\HrLeave;
use App\Models\HrReport;
use App\Models\StaffDepartment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HrReportController extends Controller
{
    public function index()
    {
        return view('admin.hr.reports', [
            'reports' => HrReport::latest('id')->paginate(20),
            'metrics' => $this->metrics(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:hr_reports,name'],
            'type' => ['required', 'in:headcount,leaves,attendance,recruitment'],
        ]);

        $report = HrReport::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'type' => $data['type'],
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', 'Report registered.');
    }

    public function show(HrReport $report)
    {
        return view('admin.hr.report_show', [
            'report' => $report,
            'rows' => $this->run($report),
        ]);
    }

    protected function run(HrReport $report): array
    {
        return match ($report->type) {
            'headcount' => Employee::with(['department', 'position'])
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->get()
                ->map(fn ($r) => ['label' => $r->status, 'total' => (int) $r->total])
                ->all(),
            'leaves' => HrLeave::selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->get()
                ->map(fn ($r) => ['label' => $r->status, 'total' => (int) $r->total])
                ->all(),
            'attendance' => HrAttendance::selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->get()
                ->map(fn ($r) => ['label' => $r->status, 'total' => (int) $r->total])
                ->all(),
            'recruitment' => HrJobPost::selectRaw('is_active, COUNT(*) as total')
                ->groupBy('is_active')
                ->get()
                ->map(fn ($r) => ['label' => $r->is_active ? 'open' : 'closed', 'total' => (int) $r->total])
                ->all(),
            default => [],
        };
    }

    protected function metrics(): array
    {
        return [
            'headcount' => Employee::count(),
            'active' => Employee::where('status', Employee::STATUS_ACTIVE)->count(),
            'pending_leaves' => HrLeave::where('status', 'pending')->count(),
            'attendance_today' => HrAttendance::whereDate('attendance_date', now()->toDateString())->count(),
            'open_jobs' => HrJobPost::where('is_active', true)->count(),
            'candidates' => HrCandidate::count(),
            'departments' => StaffDepartment::count(),
        ];
    }
}
