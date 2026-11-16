<?php

namespace App\Console\Commands;

use App\Services\Intelligence\AnomalyDetectionService;
use App\Services\Intelligence\MarketplaceIntelligenceService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class IntelligenceRun extends Command
{
    protected $signature = 'intelligence:run {--backfill= : Days to backfill snapshots for} {--detect-date= : Date (Y-m-d) to run detection for}';

    protected $description = 'Snapshot marketplace metrics and run anomaly detection.';

    public function handle(MarketplaceIntelligenceService $intelligence, AnomalyDetectionService $detector): int
    {
        if ($days = $this->option('backfill')) {
            $written = $intelligence->backfill((int) $days);
            $this->info("Metric snapshots written: {$written}.");
        } else {
            $written = $intelligence->snapshot(now());
            $this->info("Metric snapshots written for today: {$written}.");
        }

        $date = $this->option('detect-date') ? Carbon::parse($this->option('detect-date')) : now();
        $result = $detector->detect($date);
        $this->info("Anomaly detection for {$date->toDateString()}: {$result['evaluated']} rules evaluated, {$result['detected']} anomalies.");

        return self::SUCCESS;
    }
}
