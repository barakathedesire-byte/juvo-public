<?php

namespace App\Automation\Actions;

use App\Automation\AutomationAction;
use App\Models\AutomationRun;
use App\Services\Sales\AgentFraudService;

class AgentFraudScanAction implements AutomationAction
{
    public function key(): string
    {
        return 'agent_fraud_scan';
    }

    public function label(): string
    {
        return 'Scan agents for fraud signals';
    }

    public function mutates(): bool
    {
        return true;
    }

    public function handle(array $input, AutomationRun $run): array
    {
        return ['cases' => app(AgentFraudService::class)->scanAll()];
    }
}
