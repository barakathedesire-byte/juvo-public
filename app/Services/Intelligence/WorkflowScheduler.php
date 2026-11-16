<?php

namespace App\Services\Intelligence;

use App\Models\AutomationRun;
use App\Models\Workflow;
use Carbon\CarbonInterface;
use Cron\CronExpression;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Turns scheduled workflows into queued runs.
 *
 * {@see AutomationRunner::runDue()} only ever processes runs that already
 * exist, so without this step a `trigger_type=schedule` workflow would sit
 * idle forever. Each due tick materialises exactly one run and advances the
 * workflow's `next_run_at` from its cron expression.
 */
class WorkflowScheduler
{
    /**
     * Create runs for every scheduled workflow that is due.
     */
    public function materialiseDueRuns(int $limit = 100): int
    {
        $now = now();
        $created = 0;

        $due = Workflow::query()
            ->active()
            ->where('trigger_type', 'schedule')
            ->whereNotNull('schedule')
            ->where(fn ($q) => $q->whereNull('next_run_at')->orWhere('next_run_at', '<=', $now))
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($due as $workflow) {
            if ($this->hasRunInFlight($workflow)) {
                continue;
            }

            if ($this->isDue($workflow, $now)) {
                $this->createRun($workflow, $now);
                $created++;
            }

            $workflow->update(['next_run_at' => $this->nextRun($workflow, $now)]);
        }

        return $created;
    }

    protected function isDue(Workflow $workflow, CarbonInterface $now): bool
    {
        return $workflow->next_run_at !== null && $workflow->next_run_at->lte($now);
    }

    protected function hasRunInFlight(Workflow $workflow): bool
    {
        return $workflow->runs()
            ->where('trigger', 'schedule')
            ->whereIn('status', ['queued', 'running', 'retrying'])
            ->exists();
    }

    protected function createRun(Workflow $workflow, CarbonInterface $now): AutomationRun
    {
        return DB::transaction(fn () => AutomationRun::create([
            'workflow_id' => $workflow->id,
            'run_uuid' => (string) Str::uuid(),
            'trigger' => 'schedule',
            'status' => 'queued',
            'attempt' => 0,
            'max_attempts' => max(1, (int) ($workflow->max_attempts ?: 3)),
            'input' => null,
            'triggered_by' => null,
        ]));
    }

    protected function nextRun(Workflow $workflow, CarbonInterface $from): CarbonInterface
    {
        try {
            $next = (new CronExpression($workflow->schedule))->getNextRunDate($from->toDateTime());

            return Carbon::instance($next);
        } catch (Throwable) {
            return $from->copy()->addHour();
        }
    }
}
