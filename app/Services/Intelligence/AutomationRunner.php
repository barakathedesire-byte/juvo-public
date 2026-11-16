<?php

namespace App\Services\Intelligence;

use App\Models\AutomationRun;
use App\Models\AutomationRunLog;
use App\Models\Workflow;
use Illuminate\Support\Str;
use Throwable;

/**
 * Executes automation workflows with idempotency, bounded retries with
 * exponential backoff, per-run logging and a dead-letter terminal state.
 *
 * A run is identified by a globally unique UUID, so re-dispatching the same
 * logical run never double-executes side effects.
 */
class AutomationRunner
{
    public function __construct(
        protected AutomationRegistry $registry,
    ) {}

    /**
     * Create and (synchronously) process a new run for a workflow.
     *
     * @param  array<string, mixed>  $input
     */
    public function dispatch(Workflow $workflow, array $input = [], string $trigger = 'manual', ?int $actorId = null): AutomationRun
    {
        $run = AutomationRun::create([
            'workflow_id' => $workflow->id,
            'run_uuid' => (string) Str::uuid(),
            'trigger' => $trigger,
            'status' => 'queued',
            'attempt' => 0,
            'max_attempts' => max(1, (int) ($workflow->max_attempts ?: 3)),
            'input' => $input ?: null,
            'triggered_by' => $actorId,
        ]);

        return $this->process($run);
    }

    public function process(AutomationRun $run): AutomationRun
    {
        if ($run->isFinished()) {
            return $run;
        }

        $workflow = $run->workflow;
        if (! $workflow) {
            $run->update([
                'status' => 'dead_letter',
                'error' => 'workflow_missing',
                'finished_at' => now(),
                'next_retry_at' => null,
            ]);
            $this->log($run, 'error', 'Workflow missing — moved to dead letter.');

            return $run->fresh();
        }

        // Atomic claim: only the worker that flips a queued/retrying run to
        // "running" may execute it, so concurrent workers never double-run a
        // run's side effects.
        $claimed = AutomationRun::whereKey($run->getKey())
            ->whereIn('status', ['queued', 'retrying'])
            ->update(['status' => 'running']);

        if ($claimed === 0) {
            return $run->fresh();
        }

        $run->refresh();

        $timeout = max(1, (int) ($workflow->timeout_seconds ?: 60));
        @set_time_limit($timeout);

        $run->update([
            'attempt' => $run->attempt + 1,
            'started_at' => $run->started_at ?: now(),
            'next_retry_at' => null,
        ]);

        $this->log($run, 'info', 'Run started', ['attempt' => $run->attempt, 'trigger' => $run->trigger]);

        try {
            $output = [];
            $input = $run->input ?? [];

            foreach ($workflow->steps() as $index => $step) {
                $key = $step['action'] ?? null;
                if (! $key) {
                    throw new \RuntimeException('Workflow step missing action key.');
                }

                if (! $this->registry->has($key)) {
                    throw new \RuntimeException("Unregistered automation action [{$key}].");
                }

                $stepInput = array_merge($input, (array) ($step['input'] ?? []));
                $result = $this->registry->get($key)->handle($stepInput, $run);

                $output['steps'][] = ['action' => $key, 'index' => $index, 'result' => $result];
                $this->log($run, 'info', "Step [{$key}] completed.", ['result' => $result]);
            }

            $run->update([
                'status' => 'succeeded',
                'output' => $output,
                'error' => null,
                'finished_at' => now(),
                'next_retry_at' => null,
            ]);

            $this->log($run, 'info', 'Run succeeded.');
        } catch (Throwable $e) {
            $this->handleFailure($run, $e);
        }

        return $run->fresh();
    }

    protected function handleFailure(AutomationRun $run, Throwable $e): void
    {
        $workflow = $run->workflow;
        $attempt = (int) $run->attempt;
        $max = (int) ($run->max_attempts ?: 1);

        $this->log($run, 'error', 'Run failed: '.$e->getMessage(), ['exception' => $e::class]);

        if ($attempt >= $max) {
            $run->update([
                'status' => 'dead_letter',
                'error' => $e->getMessage(),
                'finished_at' => now(),
                'next_retry_at' => null,
            ]);
            $this->log($run, 'error', 'Max attempts reached — moved to dead letter.');

            return;
        }

        $delay = $this->retryDelay($workflow, $attempt);

        $run->update([
            'status' => 'retrying',
            'error' => $e->getMessage(),
            'next_retry_at' => now()->addSeconds($delay),
        ]);

        $this->log($run, 'warning', "Scheduled retry #{$attempt} in {$delay}s.");
    }

    protected function retryDelay(?Workflow $workflow, int $attempt): int
    {
        $policy = $workflow?->retry_policy ?? [];
        $base = (int) ($policy['base_seconds'] ?? 60);
        $multiplier = (float) ($policy['multiplier'] ?? 2);
        $max = (int) ($policy['max_seconds'] ?? 3600);

        $delay = (int) round($base * ($multiplier ** max(0, $attempt - 1)));

        return max(1, min($delay, $max));
    }

    /**
     * Process every due queued/retrying run.
     */
    public function runDue(int $limit = 50): int
    {
        $runs = AutomationRun::due()->orderBy('next_retry_at')->orderBy('id')->limit($limit)->get();
        $processed = 0;

        foreach ($runs as $run) {
            $this->process($run);
            $processed++;
        }

        return $processed;
    }

    protected function log(AutomationRun $run, string $level, string $message, array $context = []): void
    {
        AutomationRunLog::create([
            'run_id' => $run->id,
            'level' => $level,
            'message' => $message,
            'context' => $context ?: null,
            'created_at' => now(),
        ]);
    }
}
