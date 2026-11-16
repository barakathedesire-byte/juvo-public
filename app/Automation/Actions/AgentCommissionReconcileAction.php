<?php

namespace App\Automation\Actions;

use App\Automation\AutomationAction;
use App\Models\AgentCommissionEntry;
use App\Models\AutomationRun;
use App\Models\Order;
use App\Services\Sales\AgentCommissionEngine;

class AgentCommissionReconcileAction implements AutomationAction
{
    public function key(): string
    {
        return 'agent_commission_reconcile';
    }

    public function label(): string
    {
        return 'Reconcile agent commissions';
    }

    public function mutates(): bool
    {
        return true;
    }

    public function handle(array $input, AutomationRun $run): array
    {
        $engine = app(AgentCommissionEngine::class);

        $posted = $engine->reconcileMissing();

        $released = 0;

        Order::query()
            ->whereIn('status', ['delivered', 'completed'])
            ->where('payment_status', 'paid')
            ->whereIn('id', AgentCommissionEntry::whereIn('state', ['pending', 'provisional'])->select('order_id'))
            ->chunkById(100, function ($orders) use ($engine, &$released) {
                foreach ($orders as $order) {
                    $released += $engine->markEligibleForOrder($order);
                }
            });

        return ['posted' => $posted, 'released' => $released];
    }
}
