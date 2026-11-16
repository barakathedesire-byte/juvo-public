<?php

namespace App\Services\Integration;

use App\Models\IntegrationEvent;
use App\Services\Intelligence\WorkflowDispatcher;
use Throwable;

class IntegrationEventService
{
    public function __construct(protected WorkflowDispatcher $dispatcher) {}

    public function dispatch(string $eventType, array $payload = [], string $source = 'marketplace', ?string $target = 'logistics', ?string $idempotencyKey = null): IntegrationEvent
    {
        $eventId = $idempotencyKey ?: $eventType.':'.md5(json_encode($payload));

        $event = IntegrationEvent::firstOrCreate(
            ['event_id' => $eventId],
            [
                'event_type' => $eventType,
                'source' => $source,
                'target' => $target,
                'payload' => $payload,
                'status' => 'pending',
            ]
        );

        if ($event->status === 'processed') {
            return $event;
        }

        $event->update(['status' => 'processing', 'attempts' => $event->attempts + 1]);

        try {
            $this->dispatcher->dispatchTrigger($eventType, $payload, $payload['actor_id'] ?? null);
            $event->update(['status' => 'processed', 'processed_at' => now(), 'error' => null]);
        } catch (Throwable $e) {
            $event->update(['status' => 'failed', 'error' => $e->getMessage()]);
        }

        return $event->fresh();
    }
}
