<?php

namespace App\Automation\Actions;

use App\Automation\AutomationAction;
use App\Models\AutomationRun;
use App\Services\Intelligence\AnomalyDetectionService;
use Illuminate\Support\Carbon;

class DetectAnomaliesAction implements AutomationAction
{
    public function key(): string
    {
        return 'detect_anomalies';
    }

    public function label(): string
    {
        return 'Run anomaly detection';
    }

    public function mutates(): bool
    {
        return true;
    }

    public function handle(array $input, AutomationRun $run): array
    {
        $date = ! empty($input['date']) ? Carbon::parse($input['date']) : now()->subDay();

        return app(AnomalyDetectionService::class)->detect($date);
    }
}
