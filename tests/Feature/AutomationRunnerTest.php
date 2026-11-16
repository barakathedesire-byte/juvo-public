<?php

namespace Tests\Feature;

use App\Models\AutomationRun;
use App\Models\Workflow;
use App\Services\Intelligence\AutomationRunner;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class AutomationRunnerTest extends TestCase
{
    use DatabaseTransactions;

    protected function workflow(): Workflow
    {
        return Workflow::create([
            'key' => 'noop-'.Str::lower(Str::random(8)),
            'name' => 'Noop test workflow',
            'type' => 'general',
            'action' => 'noop',
            'definition' => ['steps' => [['action' => 'noop']]],
            'max_attempts' => 3,
        ]);
    }

    protected function makeRun(Workflow $workflow, string $status = 'queued'): AutomationRun
    {
        return AutomationRun::create([
            'workflow_id' => $workflow->id,
            'run_uuid' => (string) Str::uuid(),
            'trigger' => 'manual',
            'status' => $status,
            'attempt' => 0,
            'max_attempts' => 3,
        ]);
    }

    public function test_a_queued_run_is_claimed_and_executed_once(): void
    {
        $run = $this->makeRun($this->workflow());

        app(AutomationRunner::class)->runDue();

        $run->refresh();
        $this->assertSame('succeeded', $run->status);
        $this->assertSame(1, $run->attempt);
    }

    public function test_a_running_run_is_never_re_executed(): void
    {
        $run = $this->makeRun($this->workflow(), 'running');

        app(AutomationRunner::class)->process($run);

        $run->refresh();
        $this->assertSame('running', $run->status);
        $this->assertSame(0, $run->attempt);
        $this->assertCount(0, $run->logs);
    }

    public function test_a_finished_run_is_not_reprocessed(): void
    {
        $workflow = $this->workflow();
        $run = $this->makeRun($workflow);

        $runner = app(AutomationRunner::class);
        $runner->process($run);
        $first = $run->fresh();

        $runner->process($first);

        $this->assertSame('succeeded', $first->fresh()->status);
        $this->assertSame(1, $first->fresh()->attempt);
    }

    public function test_run_due_ignores_runs_held_by_another_worker(): void
    {
        $workflow = $this->workflow();
        $this->makeRun($workflow, 'running');

        $processed = app(AutomationRunner::class)->runDue();

        $this->assertSame(0, $processed);
    }
}
