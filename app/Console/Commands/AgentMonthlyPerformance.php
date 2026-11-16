<?php

namespace App\Console\Commands;

use App\Services\Sales\AgentCommissionEngine;
use App\Services\Sales\AgentFraudService;
use App\Services\Sales\AgentRankService;
use Illuminate\Console\Command;

class AgentMonthlyPerformance extends Command
{
    protected $signature = 'agents:monthly-performance {--period= : Reporting period (defaults to previous month)}';

    protected $description = 'Reconcile agent commissions, evaluate ranks, refresh snapshots and scan for fraud.';

    public function handle(
        AgentCommissionEngine $engine,
        AgentRankService $ranks,
        AgentFraudService $fraud,
    ): int {
        $period = $this->option('period') ?: now()->subMonthNoOverflow()->format('Y-m');

        $posted = $engine->reconcileMissing();
        $this->info("Commission entries reconciled: {$posted}.");

        $evaluated = $ranks->evaluateAll($period);
        $this->info("Agents ranked for {$period}: {$evaluated}.");

        $cases = $fraud->scanAll();
        $this->info("Fraud cases opened: {$cases}.");

        return self::SUCCESS;
    }
}
