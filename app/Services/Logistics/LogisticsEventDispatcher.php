<?php

namespace App\Services\Logistics;

use App\Models\Workflow;
use App\Services\Intelligence\AutomationRunner;

class LogisticsEventDispatcher
{
    public function __construct(protected AutomationRunner $runner) {}

    public function dispatch(string $event, array $context = []): void
    {
        $workflows = Workflow::where('scope', 'logistics')
            ->where('trigger_type', 'event')
            ->where('trigger', $event)
            ->where('is_active', true)
            ->get();

        foreach ($workflows as $workflow) {
            try {
                $this->runner->dispatch($workflow, $context, 'event', null);
            } catch (\Throwable) {
                // Event dispatch must not break the primary workflow.
            }
        }
    }
}
