<?php

namespace App\Console\Commands;

use App\Models\Workflow;
use App\Services\Intelligence\AlgorithmRegistry;
use App\Services\Intelligence\AnomalyDetectionService;
use Illuminate\Console\Command;

class IntelligenceSync extends Command
{
    protected $signature = 'intelligence:sync {--workflows : Also seed default automation workflows}';

    protected $description = 'Synchronise the algorithm registry, anomaly rules and (optionally) default automation workflows.';

    public function handle(AlgorithmRegistry $registry, AnomalyDetectionService $anomalies): int
    {
        $algorithms = $registry->sync();
        $this->info("Algorithm registry synchronised: {$algorithms} algorithms.");

        $rules = $anomalies->seedDefaultRules();
        $this->info("Anomaly rules seeded: {$rules}.");

        if ($this->option('workflows')) {
            $workflows = $this->seedWorkflows();
            $this->info("Automation workflows seeded: {$workflows}.");
        }

        return self::SUCCESS;
    }

    protected function seedWorkflows(): int
    {
        $definitions = [
            [
                'key' => 'recompute_associations_daily',
                'name' => 'Recompute product associations',
                'type' => 'recommendation',
                'action' => 'recompute_associations',
                'trigger_type' => 'schedule',
                'description' => 'Rebuilds similar/complementary product associations from orders and behaviour.',
                'config' => ['min_support' => 2],
            ],
            [
                'key' => 'refresh_marketplace_metrics_daily',
                'name' => 'Refresh marketplace metrics',
                'type' => 'intelligence',
                'action' => 'refresh_marketplace_metrics',
                'trigger_type' => 'schedule',
                'description' => 'Snapshots daily marketplace metrics for the intelligence dashboard.',
                'config' => ['days' => 1],
            ],
            [
                'key' => 'detect_anomalies_daily',
                'name' => 'Detect marketplace anomalies',
                'type' => 'intelligence',
                'action' => 'detect_anomalies',
                'trigger_type' => 'schedule',
                'description' => 'Evaluates anomaly rules against the latest metric snapshots.',
                'config' => [],
            ],
            [
                'key' => 'reindex_search_index_daily',
                'name' => 'Rebuild search index',
                'type' => 'search',
                'action' => 'reindex_search_index',
                'trigger_type' => 'schedule',
                'description' => 'Rebuilds the derived product search index from the authoritative catalog.',
                'config' => [],
            ],
            [
                'key' => 'expire_promotions_hourly',
                'name' => 'Advance & expire promotions',
                'type' => 'pricing',
                'action' => 'expire_promotions',
                'trigger_type' => 'schedule',
                'description' => 'Activates scheduled promotions, expires ended ones and releases stale budget holds.',
                'config' => [],
            ],
        ];

        $count = 0;
        foreach ($definitions as $def) {
            Workflow::updateOrCreate(
                ['key' => $def['key']],
                [
                    'name' => $def['name'],
                    'type' => $def['type'],
                    'action' => $def['action'],
                    'trigger_type' => $def['trigger_type'],
                    'description' => $def['description'],
                    'config' => $def['config'],
                    'max_attempts' => 3,
                    'timeout_seconds' => 120,
                    'retry_policy' => ['base_seconds' => 60, 'multiplier' => 2, 'max_seconds' => 3600],
                    'is_active' => true,
                ]
            );
            $count++;
        }

        return $count;
    }
}
