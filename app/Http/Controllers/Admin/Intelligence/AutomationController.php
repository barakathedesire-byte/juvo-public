<?php

namespace App\Http\Controllers\Admin\Intelligence;

use App\Http\Controllers\Controller;
use App\Models\AutomationRun;
use App\Models\Workflow;
use App\Services\Intelligence\AutomationRegistry;
use App\Services\Intelligence\AutomationRunner;
use Illuminate\Http\Request;

class AutomationController extends Controller
{
    public function __construct(
        protected AutomationRegistry $registry,
    ) {}

    public function index()
    {
        $workflows = Workflow::withCount('runs')->orderBy('name')->get();

        return view('admin.intelligence.automation.index', [
            'workflows' => $workflows,
            'actions' => $this->registry->catalogue(),
            'recentRuns' => AutomationRun::with('workflow')->orderByDesc('id')->limit(15)->get(),
            'stats' => [
                'total' => AutomationRun::count(),
                'dead_letter' => AutomationRun::where('status', 'dead_letter')->count(),
                'retrying' => AutomationRun::where('status', 'retrying')->count(),
            ],
        ]);
    }

    public function show(Workflow $workflow)
    {
        $workflow->load(['runs' => fn ($q) => $q->limit(25)]);

        return view('admin.intelligence.automation.show', [
            'workflow' => $workflow,
            'runs' => $workflow->runs,
            'actions' => $this->registry->catalogue(),
        ]);
    }

    public function runs(Request $request)
    {
        $runs = AutomationRun::with('workflow')
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('workflow_id'), fn ($q, $w) => $q->where('workflow_id', $w))
            ->orderByDesc('id')
            ->paginate(30);

        return view('admin.intelligence.automation.runs', [
            'runs' => $runs,
            'statuses' => AutomationRun::STATUSES,
        ]);
    }

    public function run(Request $request, Workflow $workflow, AutomationRunner $runner)
    {
        if (! $workflow->is_active) {
            return back()->withErrors(['workflow' => 'This workflow is inactive.']);
        }

        if (! $this->registry->has($workflow->actionKey())) {
            return back()->withErrors(['workflow' => 'This workflow references an unregistered action and cannot run.']);
        }

        $run = $runner->dispatch($workflow, (array) $workflow->config, 'manual', $request->user()?->id);

        \App\Services\Admin\AuditLogger::record('automation.run', $workflow, [], ['run_uuid' => $run->run_uuid, 'status' => $run->status]);

        return redirect()
            ->route('admin.automation.show', $workflow)
            ->with($run->status === 'succeeded' ? 'success' : 'error', 'Workflow run '.$run->status.'.');
    }

    public function retry(Request $request, AutomationRun $run, AutomationRunner $runner)
    {
        $run->update(['status' => 'queued', 'next_retry_at' => null, 'attempt' => 0]);
        $runner->process($run->fresh());

        \App\Services\Admin\AuditLogger::record('automation.retry', $run, [], ['status' => $run->fresh()->status]);

        return back()->with('success', 'Run re-queued.');
    }

    public function toggle(Workflow $workflow)
    {
        $workflow->update(['is_active' => ! $workflow->is_active]);
        \App\Services\Admin\AuditLogger::record('automation.toggled', $workflow, [], ['is_active' => $workflow->is_active]);

        return back()->with('success', 'Workflow status updated.');
    }
}
