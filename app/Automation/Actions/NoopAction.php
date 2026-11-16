<?php

namespace App\Automation\Actions;

use App\Automation\AutomationAction;
use App\Models\AutomationRun;

/**
 * A safe, side-effect-free step used for scaffolding and connectivity tests.
 */
class NoopAction implements AutomationAction
{
    public function key(): string
    {
        return 'noop';
    }

    public function label(): string
    {
        return 'No-op (test step)';
    }

    public function mutates(): bool
    {
        return false;
    }

    public function handle(array $input, AutomationRun $run): array
    {
        return ['ok' => true, 'echo' => $input];
    }
}
