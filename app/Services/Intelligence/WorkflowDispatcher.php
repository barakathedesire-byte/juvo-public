<?php

namespace App\Services\Intelligence;

use App\Models\Workflow;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fans a domain event out to the automation workflows registered for it.
 *
 * Trigger dispatch is deliberately best-effort: an automation must never be
 * able to break the domain operation that fired it (a vendor signup, an order
 * transition, a stock adjustment). Failures are logged and swallowed — the
 * run itself is already retried and dead-lettered by {@see AutomationRunner}.
 */
class WorkflowDispatcher
{
    public function __construct(protected AutomationRunner $runner) {}

    /**
     * Dispatch every active event workflow bound to $trigger.
     *
     * @param  array<string, mixed>  $input
     */
    public function dispatchTrigger(string $trigger, array $input = [], ?int $actorId = null): int
    {
        $workflows = Workflow::active()
            ->where('trigger', $trigger)
            ->where('trigger_type', 'event')
            ->get();

        $dispatched = 0;

        foreach ($workflows as $workflow) {
            try {
                $this->runner->dispatch($workflow, $input, 'event', $actorId);
                $dispatched++;
            } catch (Throwable $e) {
                Log::warning('Automation trigger dispatch failed.', [
                    'trigger' => $trigger,
                    'workflow_id' => $workflow->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $dispatched;
    }
}
