<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HrCandidate;
use App\Models\HrJobPost;
use App\Services\Admin\AuditLogger;
use Illuminate\Http\Request;

class HrRecruitmentController extends Controller
{
    public function __construct(protected AuditLogger $audit) {}

    public function index()
    {
        return view('admin.hr.recruitment', [
            'jobs' => HrJobPost::with(['department', 'position'])
                ->withCount('candidates')
                ->latest('id')
                ->paginate(25),
        ]);
    }

    public function storeJob(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'department_id' => ['nullable', 'exists:staff_departments,id'],
            'position_id' => ['nullable', 'exists:positions,id'],
            'description' => ['nullable', 'string', 'max:5000'],
            'employment_type' => ['nullable', 'in:full_time,part_time,contract,intern'],
            'location' => ['nullable', 'string', 'max:255'],
            'openings' => ['nullable', 'integer', 'min:1', 'max:100'],
            'closes_at' => ['nullable', 'date'],
        ]);

        $job = HrJobPost::create($data + ['is_active' => true]);

        $this->audit->log('hr.recruitment.job_created', $job, [], ['title' => $job->title]);

        return back()->with('success', 'Job post created.');
    }

    public function showJob(HrJobPost $job)
    {
        $job->load(['department', 'position']);

        return view('admin.hr.recruitment_job', [
            'job' => $job,
            'candidates' => $job->candidates()->latest('id')->paginate(25),
            'statuses' => HrCandidate::STATUSES,
        ]);
    }

    public function updateCandidate(HrJobPost $job, HrCandidate $candidate, Request $request)
    {
        abort_unless($candidate->job_post_id === $job->id, 404);

        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', HrCandidate::STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $before = ['status' => $candidate->status];
        $candidate->update($data);

        $this->audit->log('hr.recruitment.candidate_updated', $candidate, $before, ['status' => $candidate->status]);

        return back()->with('success', 'Candidate updated.');
    }

    public function storeCandidate(Request $request)
    {
        $data = $request->validate([
            'job_post_id' => ['required', 'exists:hr_job_posts,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        $candidate = HrCandidate::create($data + ['status' => 'applied']);

        $this->audit->log('hr.recruitment.candidate_applied', $candidate, [], []);

        return back()->with('success', 'Candidate added.');
    }
}
