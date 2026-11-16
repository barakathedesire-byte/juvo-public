<?php

namespace App\Services\Vendor;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\Intelligence\WorkflowDispatcher;
use App\Services\Store\InventoryService;
use App\Services\Store\PaymentService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderWorkflowService
{
    /** Methods whose cash is only collected on delivery. */
    protected const COD_METHODS = ['cod', 'payondelivery', 'pod', 'pay_on_delivery', 'cash_on_delivery'];

    public function __construct(
        protected InventoryService $inventory,
        protected VendorFinanceService $finance,
        protected \App\Services\Integration\IntegrationEventService $integrationEvents,
    ) {}

    /**
     * Move an order to a new status, enforcing the state machine, writing an
     * audit/status-history row, and applying side effects (timestamps, restock).
     *
     * @throws RuntimeException when the transition is not allowed.
     */
    public function transition(Order $order, string $to, ?string $reason = null, string $actorType = 'vendor', ?int $actorId = null): Order
    {
        if ($order->status === $to) {
            return $order;
        }

        if (! $order->canTransitionTo($to)) {
            throw new RuntimeException("Cannot move order #{$order->id} from '{$order->status}' to '{$to}'.");
        }

        $from = $order->status;

        $result = DB::transaction(function () use ($order, $to, $reason, $actorType, $actorId, $from) {
            $attributes = ['status' => $to];

            if (in_array($to, ['dispatched', 'ready_for_pickup', 'delivered', 'completed'], true)) {
                $attributes['fulfilled_at'] = $order->fulfilled_at ?? now();
            }

            if ($to === 'ready_for_pickup') {
                $attributes['pickup_ready_at'] = $order->pickup_ready_at ?? now();
            }

            if ($to === 'rider_arrived') {
                $attributes['rider_arrived_at'] = $order->rider_arrived_at ?? now();
            }

            if (in_array($to, ['delivered', 'completed'], true)) {
                $attributes['delivered_at'] = $order->delivered_at ?? now();
            }

            if (in_array($to, ['canceled', 'returned'], true)) {
                $attributes['canceled_at'] = now();
                if ($reason) {
                    $attributes['cancel_reason'] = $reason;
                }
                $this->restock($order, $to);
            }

            $order->update($attributes);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => $from,
                'to_status' => $to,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'reason' => $reason,
            ]);

            return $order->refresh();
        });

        $this->syncEarnings($result, $to, $reason);
        $this->notifyCustomer($result, $from, $to, $reason);

        $this->integrationEvents->dispatch('order_status_change', [
            'order_id' => $result->id,
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $actorId,
        ], 'marketplace', 'logistics', 'order:'.$result->id.':'.$from.':'.$to);

        return $result;
    }

    /**
     * Keep the vendor earnings ledger in step with the order lifecycle:
     * completed orders release earnings for payout, cancellations and returns
     * reverse them.
     */
    protected function syncEarnings(Order $order, string $to, ?string $reason = null): void
    {
        try {
            if ($to === 'completed') {
                $this->collectCashOnDelivery($order);
                $this->finance->markAvailableForOrder($order);
            } elseif (in_array($to, ['canceled', 'returned'], true)) {
                $this->finance->reverseForOrder($order);
            }
        } catch (\Throwable $e) {
            \Log::warning('Vendor earnings sync failed for order #'.$order->id.': '.$e->getMessage());
        }

        $this->syncAgentCommissions($order, $to, $reason);
    }

    /**
     * Keep the agent commission ledger in step with the order lifecycle:
     * delivered/paid orders release agent commissions for payout; cancellations
     * and returns reverse them. Failures never break the order transition.
     */
    protected function syncAgentCommissions(Order $order, string $to, ?string $reason = null): void
    {
        try {
            $engine = app(\App\Services\Sales\AgentCommissionEngine::class);

            if (in_array($to, ['delivered', 'completed'], true)) {
                $engine->markEligibleForOrder($order->refresh());
            } elseif (in_array($to, ['canceled', 'returned'], true)) {
                $engine->reverseForOrder($order, $reason);
            }
        } catch (\Throwable $e) {
            \Log::warning('Agent commission sync failed for order #'.$order->id.': '.$e->getMessage());
        }
    }

    /**
     * Cash-on-delivery orders are only collected when fulfilment completes, so
     * settle the pending payment at that point. Instant-gateway orders are
     * already paid and are left untouched.
     */
    protected function collectCashOnDelivery(Order $order): void
    {
        if ($order->payment_status === 'paid') {
            return;
        }

        $method = strtolower((string) $order->payment_method);

        if ($method !== '' && ! in_array($method, self::COD_METHODS, true)) {
            return;
        }

        app(PaymentService::class)->markCompleted($order, ['source' => 'cash_on_delivery'], 'COD-'.$order->id);
    }

    /**
     * Email + in-app notify the customer about a status change. Failures are
     * logged but never break the order transition.
     */
    protected function notifyCustomer(Order $order, string $from, string $to, ?string $reason): void
    {
        $customer = $order->customer;

        if (! $customer || empty($customer->email)) {
            return;
        }

        try {
            $customer->notify(new \App\Notifications\OrderStatusNotification($order, $from, $to));
        } catch (\Throwable $e) {
            \Log::warning('Order status notification failed for order #'.$order->id.': '.$e->getMessage());
        }
    }

    /**
     * Return every ordered line back to inventory (cancel / return).
     */
    public function restock(Order $order, string $reason): void
    {
        foreach ($order->details()->with('variant')->get() as $detail) {
            if ($detail->variant) {
                $this->inventory->restock($detail->variant, (int) $detail->quantity, 'return', [
                    'reference_type' => Order::class,
                    'reference_id' => $order->id,
                    'reason' => 'Order '.$reason,
                ]);
            }
        }
    }
}
