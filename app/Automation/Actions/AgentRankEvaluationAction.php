<?php

namespace App\Automation\Actions;

use App\Automation\AutomationAction;
use App\Models\AutomationRun;
use App\Models\SalesRep;
use App\Services\Sales\AgentRankService;

class AgentRankEvaluationAction implements AutomationAction
{
    public function key(): string
    {
        return 'agent_rank_evaluation';
    }

    public function label(): string
    {
        return 'Evaluate agent ranks';
    }

    public function mutates(): bool
    {
        return true;
    }

    public function handle(array $input, AutomationRun $run): array
    {
        $service = app(AgentRankService::class);

        if (! empty($input['agent_id'])) {
            $agent = SalesRep::find((int) $input['agent_id']);

            if (! $agent) {
                return ['error' => 'agent_not_found'];
            }

            $history = $service->evaluate($agent, $input['period'] ?? null);

            return ['evaluated' => 1, 'history_id' => $history?->id];
        }

        return ['evaluated' => $service->evaluateAll($input['period'] ?? null)];
    }
}
