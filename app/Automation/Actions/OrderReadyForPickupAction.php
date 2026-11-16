<?php

namespace App\Automation\Actions;

use App\Automation\AutomationAction;
use App\Models\AutomationRun;
use App\Models\Order;
use App\Services\Fleetbase\OrderSync;

/**
 * Hand an order that is ready for pickup over to the delivery partner.
 *
 * Idempotent: {@see OrderSync::push()} skips orders that already carry a
 * Fleetbase order id, and the push is skipped entirely when the integration
 * is not configured so an unconfigured platform never makes network calls.
 */
class OrderReadyForPickupAction implements AutomationAction
{
    public function key(): string
    {
        return 'order_ready_for_pickup';
    }

    public function label(): string
    {
        return 'Order ready for pickup';
    }

    public function mutates(): bool
    {
        return true;
    }

    public function handle(array $input, AutomationRun $run): array
    {
        $config = (array) $run->workflow?->config;

        $order = Order::find($input['order_id'] ?? null);
        if (! $order) {
            return ['ok' => false, 'reason' => 'order_not_found'];
        }

        if ($order->status !== 'ready_for_pickup') {
            return [
                'ok' => true,
                'outcome' => 'skipped',
                'reason' => 'not_ready_for_pickup',
                'order_id' => $order->id,
                'status' => $order->status,
            ];
        }

        if (! ($input['push_fleetbase'] ?? $config['push_fleetbase'] ?? false)) {
            return ['ok' => true, 'outcome' => 'skipped', 'reason' => 'push_disabled', 'order_id' => $order->id];
        }

        if (! config('fleetbase.api_key') || ! config('fleetbase.base_url')) {
            return [
                'ok' => true,
                'outcome' => 'skipped',
                'reason' => 'fleetbase_not_configured',
                'order_id' => $order->id,
            ];
        }

        $result = app(OrderSync::class)->push($order);

        return array_merge($result, [
            'order_id' => $order->id,
            'outcome' => $result['skipped'] ? 'already_pushed' : 'pushed',
        ]);
    }
}
