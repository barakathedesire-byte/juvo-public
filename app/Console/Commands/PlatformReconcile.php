<?php

namespace App\Console\Commands;

use App\Services\Admin\PlatformIntegrityService;
use Illuminate\Console\Command;

class PlatformReconcile extends Command
{
    protected $signature = 'platform:reconcile {--strict : Exit with a non-zero code if any check fails}';

    protected $description = 'Run platform integrity and reconciliation checks';

    public function handle(PlatformIntegrityService $integrity): int
    {
        $checks = $integrity->checks();

        $this->table(
            ['Check', 'Status', 'Count', 'Detail'],
            array_map(fn ($check) => [
                $check['label'],
                strtoupper($check['status']),
                $check['count'],
                $check['detail'],
            ], $checks)
        );

        $summary = $integrity->summary();

        $this->line(sprintf(
            'Checks: %d  OK: %d  WARN: %d  FAIL: %d',
            $summary['total'], $summary['ok'], $summary['warn'], $summary['fail']
        ));

        if ($this->option('strict') && $summary['fail'] > 0) {
            $this->error('Reconciliation failed.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
