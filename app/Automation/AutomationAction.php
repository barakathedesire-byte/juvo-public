<?php

namespace App\Automation;

use App\Models\AutomationRun;

/**
 * A single, registered, side-effect-scoped automation step. Actions must be
 * idempotent and must never be able to escalate their own privileges: they
 * operate with system scope and are responsible for their own record checks.
 */
interface AutomationAction
{
    /**
     * Stable key referenced by workflow definitions.
     */
    public function key(): string;

    /**
     * Human label for the automation catalogue.
     */
    public function label(): string;

    /**
     * Whether this action changes platform state (informational for the UI).
     */
    public function mutates(): bool;

    /**
     * Execute the action.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed> structured result persisted on the run
     */
    public function handle(array $input, AutomationRun $run): array;
}
