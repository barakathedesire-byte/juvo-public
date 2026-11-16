<?php

namespace App\Services\Logistics;

use App\Models\Logistics\DeliveryEvent;
use App\Models\Order;
use App\Models\Rider\Rider;
use App\Services\Admin\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns every delivery lifecycle change so the admin portal and the rider app
 * cannot diverge: one transaction per action, one audit record, one timeline
 * entry, and an explicit status-transition check.
 */
class DeliveryService
{
    public function __construct(private readonly RiderPayoutService $riderPayouts) {}

    public function assign(Order $order, Rider $rider, ?Model $actor = null, ?string $note = null): Order
    {
        if ($rider->eligibilityReason() !== null) {
            throw ValidationException::withMessages([
                'rider' => $rider->eligibilityReason(),
            ]);
        }

        return $this->apply($order, function (Order $locked) use ($rider, $actor, $note) {
            if (! in_array($locked->logistics_status ?? 'pending', ['pending', 'created', 'failed', 'assigned', 'accepted'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'This delivery can no longer be assigned.',
                ]);
            }

            $before = $locked->only(['assigned_driver_id', 'logistics_status']);

            $locked->forceFill([
                'assigned_driver_id' => $rider->id,
                'driver_name' => $rider->full_name,
                'driver_phone' => $rider->phone,
                'assigned_at' => now(),
                'accepted_at' => null,
                'logistics_status' => 'assigned',
                'failed_at' => null,
                'failed_reason' => null,
                'failure_note' => null,
            ])->save();

            $this->event(
                $locked,
                $before['assigned_driver_id'] ? DeliveryEvent::TYPE_REASSIGNED : DeliveryEvent::TYPE_ASSIGNED,
                $before['logistics_status'] ?? null,
                'assigned',
                $actor,
                $note,
                ['rider_id' => $rider->id, 'rider_name' => $rider->full_name],
            );

            return $locked;
        }, $actor);
    }

    public function unassign(Order $order, ?Model $actor = null, ?string $reason = null): Order
    {
        return $this->apply($order, function (Order $locked) use ($actor, $reason) {
            if (! $locked->assigned_driver_id) {
                throw ValidationException::withMessages(['rider' => 'No rider is assigned to this delivery.']);
            }

            $before = $locked->only(['assigned_driver_id', 'logistics_status']);

            $locked->forceFill([
                'assigned_driver_id' => null,
                'driver_name' => null,
                'driver_phone' => null,
                'assigned_at' => null,
                'accepted_at' => null,
                'logistics_status' => 'pending',
            ])->save();

            $this->event($locked, DeliveryEvent::TYPE_UNASSIGNED, $before['logistics_status'] ?? null, 'pending', $actor, $reason);

            return $locked;
        }, $actor);
    }

    public function accept(Order $order, Rider $rider): Order
    {
        return $this->asRider($order, $rider, function (Order $locked) use ($rider) {
            $this->assertStatus($locked, ['assigned']);
            $this->assertAssignedTo($locked, $rider);

            $locked->forceFill([
                'logistics_status' => 'accepted',
                'accepted_at' => now(),
            ])->save();

            $this->event($locked, DeliveryEvent::TYPE_ACCEPTED, 'assigned', 'accepted', $rider);

            return $locked;
        });
    }

    public function markArrivedAtPickup(Order $order, Rider $rider): Order
    {
        return $this->asRider($order, $rider, function (Order $locked) use ($rider) {
            $this->assertStatus($locked, ['accepted']);
            $this->assertAssignedTo($locked, $rider);

            return $this->recordArrival($locked, $rider, DeliveryEvent::TYPE_ARRIVED_PICKUP);
        });
    }

    public function markArrivedAtDropoff(Order $order, Rider $rider): Order
    {
        return $this->asRider($order, $rider, function (Order $locked) use ($rider) {
            $this->assertStatus($locked, ['in_transit']);
            $this->assertAssignedTo($locked, $rider);

            return $this->recordArrival($locked, $rider, DeliveryEvent::TYPE_ARRIVED_DROPOFF);
        });
    }

    public function decline(Order $order, Rider $rider, ?string $reason = null): Order
    {
        return $this->asRider($order, $rider, function (Order $locked) use ($rider, $reason) {
            $this->assertStatus($locked, ['assigned']);
            $this->assertAssignedTo($locked, $rider);

            $locked->forceFill([
                'assigned_driver_id' => null,
                'driver_name' => null,
                'driver_phone' => null,
                'assigned_at' => null,
                'accepted_at' => null,
                'logistics_status' => 'pending',
            ])->save();

            $this->event($locked, DeliveryEvent::TYPE_DECLINED, 'assigned', 'pending', $rider, $reason);

            return $locked;
        });
    }

    public function markPickedUp(Order $order, Model $actor, ?string $note = null): Order
    {
        return $this->apply($order, function (Order $locked) use ($actor, $note) {
            $this->assertStatus($locked, ['accepted', 'assigned']);

            if (! $locked->assigned_driver_id) {
                throw ValidationException::withMessages(['rider' => 'Assign a rider before pickup.']);
            }

            $from = $locked->logistics_status;

            $locked->forceFill([
                'logistics_status' => 'picked_up',
                'picked_up_at' => $locked->picked_up_at ?? now(),
            ])->save();

            $this->event($locked, DeliveryEvent::TYPE_PICKED_UP, $from, 'picked_up', $actor, $note);

            return $locked;
        }, $actor);
    }

    public function markInTransit(Order $order, Model $actor, ?string $note = null): Order
    {
        return $this->apply($order, function (Order $locked) use ($actor, $note) {
            $this->assertStatus($locked, ['picked_up']);

            $locked->forceFill(['logistics_status' => 'in_transit'])->save();

            $this->event($locked, DeliveryEvent::TYPE_IN_TRANSIT, 'picked_up', 'in_transit', $actor, $note);

            return $locked;
        }, $actor);
    }

    /**
     * @param  array{receiver_name?:string|null,photo_url?:string|null,notes?:string|null,cod_collected?:bool}  $data
     */
    public function markDelivered(Order $order, Model $actor, array $data = []): Order
    {
        return $this->apply($order, function (Order $locked) use ($actor, $data) {
            $this->assertStatus($locked, ['picked_up', 'in_transit', 'accepted', 'assigned']);

            $receiver = trim((string) ($data['receiver_name'] ?? ''));
            if ($receiver === '') {
                throw ValidationException::withMessages([
                    'receiver_name' => 'Record who received the delivery.',
                ]);
            }

            $from = $locked->logistics_status;
            $codCollected = (bool) ($data['cod_collected'] ?? false) && $locked->isCod() && $locked->payment_status !== 'paid';

            $locked->forceFill([
                'logistics_status' => 'delivered',
                'delivered_at' => now(),
                'pod_delivered_at' => now(),
                'pod_receiver_name' => $receiver,
                'pod_notes' => $data['notes'] ?? null,
                'pod_photo_url' => $data['photo_url'] ?? $locked->pod_photo_url,
                'payment_status' => $codCollected ? 'paid' : $locked->payment_status,
                'cod_amount' => $codCollected ? $locked->total_amount : $locked->cod_amount,
                'cod_collected_at' => $codCollected ? now() : $locked->cod_collected_at,
                'returned_at' => null,
            ])->save();

            if ($locked->canTransitionTo('delivered')) {
                $locked->forceFill(['status' => 'delivered'])->save();
            }

            if ($locked->assigned_driver_id) {
                Rider::whereKey($locked->assigned_driver_id)->increment('total_deliveries');
            }

            $this->event($locked, DeliveryEvent::TYPE_DELIVERED, $from, 'delivered', $actor, $data['notes'] ?? null, [
                'receiver_name' => $receiver,
                'cod_collected' => $codCollected,
            ]);

            if ($codCollected) {
                $this->event($locked, DeliveryEvent::TYPE_COD_COLLECTED, null, null, $actor, null, [
                    'amount' => (float) $locked->cod_amount,
                ]);
            }

            $this->riderPayouts->recordDeliveryEarning($locked, $actor);

            return $locked;
        }, $actor);
    }

    public function markFailed(Order $order, Model $actor, string $reason, ?string $note = null): Order
    {
        if (! array_key_exists($reason, Order::FAILURE_REASONS)) {
            throw ValidationException::withMessages(['reason' => 'Choose a valid failure reason.']);
        }

        return $this->apply($order, function (Order $locked) use ($actor, $reason, $note) {
            $this->assertStatus($locked, ['assigned', 'accepted', 'picked_up', 'in_transit']);

            $from = $locked->logistics_status;

            $locked->forceFill([
                'logistics_status' => 'failed',
                'failed_at' => now(),
                'failed_reason' => $reason,
                'failure_note' => $note,
                'delivery_attempts' => ((int) $locked->delivery_attempts) + 1,
            ])->save();

            $this->event($locked, DeliveryEvent::TYPE_FAILED, $from, 'failed', $actor, $note, [
                'reason' => $reason,
                'attempt' => (int) $locked->delivery_attempts,
            ]);

            return $locked;
        }, $actor);
    }

    public function reschedule(Order $order, Model $actor, string $date, ?string $note = null): Order
    {
        return $this->apply($order, function (Order $locked) use ($actor, $date, $note) {
            $this->assertStatus($locked, ['failed', 'assigned', 'accepted', 'pending']);

            $from = $locked->logistics_status;

            $locked->forceFill([
                'scheduled_for' => $date,
                'logistics_status' => $locked->assigned_driver_id ? 'assigned' : 'pending',
                'failed_at' => null,
                'failed_reason' => null,
            ])->save();

            $this->event($locked, DeliveryEvent::TYPE_RESCHEDULED, $from, $locked->logistics_status, $actor, $note, [
                'scheduled_for' => $date,
            ]);

            return $locked;
        }, $actor);
    }

    public function markReturned(Order $order, Model $actor, ?string $note = null): Order
    {
        return $this->apply($order, function (Order $locked) use ($actor, $note) {
            $this->assertStatus($locked, ['failed', 'delivered']);

            $from = $locked->logistics_status;

            $locked->forceFill([
                'logistics_status' => 'returned',
                'returned_at' => now(),
                'assigned_driver_id' => null,
            ])->save();

            if ($locked->canTransitionTo('returned')) {
                $locked->forceFill(['status' => 'returned'])->save();
            }

            $this->event($locked, DeliveryEvent::TYPE_RETURNED, $from, 'returned', $actor, $note);

            $this->riderPayouts->reverseDeliveryEarning($locked, $actor, $note ?: 'Delivery returned to sender.');

            return $locked;
        }, $actor);
    }

    public function cancel(Order $order, Model $actor, ?string $note = null): Order
    {
        return $this->apply($order, function (Order $locked) use ($actor, $note) {
            $this->assertStatus($locked, ['pending', 'created', 'assigned', 'accepted', 'failed']);

            $from = $locked->logistics_status;

            $locked->forceFill([
                'logistics_status' => 'cancelled',
                'canceled_at' => now(),
                'cancel_reason' => $note,
                'assigned_driver_id' => null,
            ])->save();

            $this->event($locked, DeliveryEvent::TYPE_CANCELLED, $from, 'cancelled', $actor, $note);

            return $locked;
        }, $actor);
    }

    public function recordCodCollection(Order $order, Model $actor, ?float $amount = null, ?string $note = null): Order
    {
        return $this->apply($order, function (Order $locked) use ($actor, $amount, $note) {
            if (! $locked->isCod()) {
                throw ValidationException::withMessages(['order' => 'This order is not cash on delivery.']);
            }

            if ($locked->logistics_status !== 'delivered') {
                throw ValidationException::withMessages(['order' => 'Cash can only be recorded after delivery.']);
            }

            if ($locked->cod_collected_at) {
                throw ValidationException::withMessages(['order' => 'Cash was already recorded as collected.']);
            }

            $value = $amount !== null ? (float) $amount : (float) $locked->total_amount;

            $locked->forceFill([
                'cod_amount' => $value,
                'cod_collected_at' => now(),
                'payment_status' => 'paid',
            ])->save();

            $this->event($locked, DeliveryEvent::TYPE_COD_COLLECTED, null, null, $actor, $note, ['amount' => $value]);

            return $locked;
        }, $actor);
    }

    public function remitCod(Order $order, Model $actor, string $reference, ?string $note = null): Order
    {
        return $this->apply($order, function (Order $locked) use ($actor, $reference, $note) {
            if (! $locked->cod_collected_at) {
                throw ValidationException::withMessages(['order' => 'No collected cash is recorded for this order.']);
            }

            if ($locked->cod_remitted_at) {
                throw ValidationException::withMessages(['order' => 'This cash was already reconciled.']);
            }

            $locked->forceFill([
                'cod_remitted_at' => now(),
                'cod_remittance_ref' => $reference,
            ])->save();

            $this->event($locked, DeliveryEvent::TYPE_COD_REMITTED, null, null, $actor, $note, [
                'reference' => $reference,
                'amount' => (float) $locked->cod_amount,
            ]);

            return $locked;
        }, $actor);
    }

    public function addNote(Order $order, Model $actor, string $note): Order
    {
        return $this->apply($order, function (Order $locked) use ($actor, $note) {
            $this->event($locked, DeliveryEvent::TYPE_NOTE, $locked->logistics_status, $locked->logistics_status, $actor, $note);

            return $locked;
        }, $actor);
    }

    protected function asRider(Order $order, Rider $rider, callable $callback): Order
    {
        if ($rider->eligibilityReason() !== null) {
            throw ValidationException::withMessages(['rider' => $rider->eligibilityReason()]);
        }

        return $this->apply($order, $callback, $rider);
    }

    protected function apply(Order $order, callable $callback, ?Model $actor = null): Order
    {
        return DB::transaction(function () use ($order, $callback, $actor) {
            $locked = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($actor instanceof Rider) {
                $this->assertAssignedTo($locked, $actor);
            }

            $before = $locked->only(['logistics_status', 'assigned_driver_id', 'payment_status']);
            $result = $callback($locked);

            AuditLogger::record(
                'logistics.delivery.'.$result->logistics_status,
                $result,
                $before,
                $result->only(['logistics_status', 'assigned_driver_id', 'payment_status', 'cod_amount']),
                null,
                $actor,
            );

            $order->setRawAttributes($result->getAttributes(), true);

            return $result;
        });
    }

    protected function assertStatus(Order $order, array $allowed): void
    {
        if (! in_array($order->logistics_status ?? 'pending', $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => 'This action is not available while the delivery is "'.$order->logisticsStatusLabel().'".',
            ]);
        }
    }

    protected function assertAssignedTo(Order $order, Rider $rider): void
    {
        if ((int) $order->assigned_driver_id !== (int) $rider->id) {
            throw ValidationException::withMessages(['rider' => 'This delivery is not assigned to you.']);
        }
    }

    protected function recordArrival(Order $order, Rider $rider, string $type): Order
    {
        $alreadyRecorded = DeliveryEvent::where('order_id', $order->id)
            ->where('type', $type)
            ->exists();

        if ($alreadyRecorded) {
            return $order;
        }

        $this->event($order, $type, $order->logistics_status, $order->logistics_status, $rider);

        return $order;
    }

    protected function event(
        Order $order,
        string $type,
        ?string $from,
        ?string $to,
        ?Model $actor,
        ?string $note = null,
        array $meta = [],
    ): DeliveryEvent {
        return DeliveryEvent::create([
            'order_id' => $order->id,
            'rider_id' => $order->assigned_driver_id,
            'type' => $type,
            'from_status' => $from,
            'to_status' => $to,
            'actor_type' => $actor?->getMorphClass(),
            'actor_id' => $actor?->getKey(),
            'actor_name' => $this->actorName($actor),
            'note' => $note,
            'meta' => $meta ?: null,
        ]);
    }

    protected function actorName(?Model $actor): ?string
    {
        if (! $actor) {
            return 'System';
        }

        return $actor->name ?? $actor->full_name ?? 'User #'.$actor->getKey();
    }
}
