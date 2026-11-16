<?php

namespace App\Support\Orders;

use App\Models\Order;
use Illuminate\Support\Carbon;

/**
 * Turns an order's raw status history into the customer-facing delivery
 * journey, which differs for door delivery and pickup-station orders.
 */
class OrderProgress
{
    /**
     * Ordered milestones for each fulfillment type.
     */
    public const MILESTONES = [
        'delivery' => ['pending', 'processing', 'packed', 'dispatched', 'rider_arrived', 'completed'],
        'pickup' => ['pending', 'processing', 'packed', 'dispatched', 'ready_for_pickup', 'completed'],
    ];

    public const LABELS = [
        'delivery' => [
            'pending' => 'Order placed',
            'processing' => 'Processing',
            'packed' => 'Packed',
            'dispatched' => 'Shipped',
            'rider_arrived' => 'Rider arrived',
            'completed' => 'Delivered',
        ],
        'pickup' => [
            'pending' => 'Order placed',
            'processing' => 'Processing',
            'packed' => 'Packed',
            'dispatched' => 'In transit to pickup station',
            'ready_for_pickup' => 'Ready for pickup',
            'completed' => 'Collected',
        ],
    ];

    public static function mapStatus(string $status, bool $pickup): string
    {
        return match ($status) {
            'accepted' => 'processing',
            'delivered', 'completed' => 'completed',
            'ready_for_pickup' => $pickup ? 'ready_for_pickup' : 'dispatched',
            default => $status,
        };
    }

    /**
     * @return array<int, array{key:string,label:string,state:string,at:?Carbon}>
     */
    public static function milestones(Order $order): array
    {
        $pickup = $order->isPickup();
        $type = $pickup ? 'pickup' : 'delivery';

        if (in_array($order->status, ['canceled', 'returned'], true)) {
            return [[
                'key' => $order->status,
                'label' => $order->status === 'canceled' ? 'Order canceled' : 'Order returned',
                'state' => 'canceled',
                'at' => $order->canceled_at ?? $order->updated_at,
            ]];
        }

        $keys = self::MILESTONES[$type];
        $labels = self::LABELS[$type];
        $currentKey = self::mapStatus((string) $order->status, $pickup);
        $currentIndex = array_search($currentKey, $keys, true);
        $currentIndex = $currentIndex === false ? 0 : $currentIndex;

        $timestamps = self::timestamps($order, $pickup);

        $milestones = [];
        foreach ($keys as $index => $key) {
            $state = $index < $currentIndex ? 'done' : ($index === $currentIndex ? 'current' : 'pending');

            $milestones[] = [
                'key' => $key,
                'label' => $labels[$key] ?? ucwords(str_replace('_', ' ', $key)),
                'state' => $state,
                'at' => $timestamps[$key] ?? null,
            ];
        }

        return $milestones;
    }

    /**
     * When each milestone was reached, derived from the status history and the
     * dedicated fulfillment timestamps.
     *
     * @return array<string, Carbon>
     */
    protected static function timestamps(Order $order, bool $pickup): array
    {
        $timestamps = ['pending' => $order->created_at];

        foreach ($order->statusHistories as $history) {
            if (! $history->created_at) {
                continue;
            }

            $key = self::mapStatus((string) $history->to_status, $pickup);

            if (! isset($timestamps[$key]) || $history->created_at->lt($timestamps[$key])) {
                $timestamps[$key] = $history->created_at;
            }
        }

        if ($order->pickup_ready_at && ! isset($timestamps['ready_for_pickup'])) {
            $timestamps['ready_for_pickup'] = $order->pickup_ready_at;
        }

        if ($order->rider_arrived_at && ! isset($timestamps['rider_arrived'])) {
            $timestamps['rider_arrived'] = $order->rider_arrived_at;
        }

        if ($order->delivered_at && ! isset($timestamps['completed'])) {
            $timestamps['completed'] = $order->delivered_at;
        }

        return $timestamps;
    }
}
