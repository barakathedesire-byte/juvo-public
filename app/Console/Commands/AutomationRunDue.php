<?php

namespace App\Console\Commands;

use App\Services\Intelligence\AutomationRunner;
use App\Services\Intelligence\WorkflowScheduler;
use Illuminate\Console\Command;

class AutomationRunDue extends Command
{
    protected $signature = 'automation:run {--limit=50} {--no-dispatch : Materialise scheduled runs without executing them}';

    protected $description = 'Materialise scheduled workflow runs, then process due automation runs (including retries).';

    public function handle(WorkflowScheduler $scheduler, AutomationRunner $runner): int
    {
        $created = $scheduler->materialiseDueRuns();
        $this->info("Scheduled runs created: {$created}.");

        if ($this->option('no-dispatch')) {
            return self::SUCCESS;
        }

        $processed = $runner->runDue((int) $this->option('limit'));
        $this->info("Automation runs processed: {$processed}.");

        return self::SUCCESS;
    }
}
