<?php

namespace App\Services\Notifications;

use App\Models\Customer;
use App\Models\Logistics\LogisticsAdmin;
use App\Models\Notification;
use App\Models\Order;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Collection;

class NotificationService
{
    public const TYPE_ORDER_PLACED = 'order_placed';

    public const TYPE_ORDER_ACCEPTED = 'order_accepted';

    public const TYPE_ORDER_PROCESSING = 'order_processing';

    public const TYPE_ORDER_PACKED = 'order_packed';

    public const TYPE_ORDER_READY_FOR_PICKUP = 'order_ready_for_pickup';

    public const TYPE_ORDER_DISPATCHED = 'order_dispatched';

    public const TYPE_ORDER_RIDER_ARRIVED = 'order_rider_arrived';

    public const TYPE_ORDER_DELIVERED = 'order_delivered';

    public const TYPE_ORDER_CANCELLED = 'order_cancelled';

    public const TYPE_ORDER_REFUNDED = 'order_refunded';

    public const TYPE_NEW_MESSAGE = 'new_message';

    public const TYPE_REVIEW_RECEIVED = 'review_received';

    public const TYPE_PROMOTION = 'promotion';

    public const TYPE_SYSTEM = 'system';

    public function notifyCustomer(Customer $customer, string $type, array $data): Notification
    {
        return Notification::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => $type,
            'notifiable_type' => Customer::class,
            'notifiable_id' => $customer->id,
            'data' => $data,
        ]);
    }

    public function notifyVendor(Vendor $vendor, string $type, array $data): Notification
    {
        return Notification::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => $type,
            'notifiable_type' => Vendor::class,
            'notifiable_id' => $vendor->id,
            'data' => $data,
        ]);
    }

    public function notifyAdmin(User $admin, string $type, array $data): Notification
    {
        return Notification::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => $type,
            'notifiable_type' => User::class,
            'notifiable_id' => $admin->id,
            'data' => $data,
        ]);
    }

    public function notifyOrderStatusChange(Order $order, string $newStatus, ?string $oldStatus = null): void
    {
        $customer = $order->customer;
        if (! $customer) {
            return;
        }

        $type = match ($newStatus) {
            'accepted' => self::TYPE_ORDER_ACCEPTED,
            'processing' => self::TYPE_ORDER_PROCESSING,
            'packed' => self::TYPE_ORDER_PACKED,
            'ready_for_pickup' => self::TYPE_ORDER_READY_FOR_PICKUP,
            'dispatched' => self::TYPE_ORDER_DISPATCHED,
            'rider_arrived' => self::TYPE_ORDER_RIDER_ARRIVED,
            'delivered' => self::TYPE_ORDER_DELIVERED,
            'completed' => self::TYPE_ORDER_DELIVERED,
            'canceled' => self::TYPE_ORDER_CANCELLED,
            'returned' => self::TYPE_ORDER_REFUNDED,
            default => null,
        };

        if (! $type) {
            return;
        }

        $titles = [
            self::TYPE_ORDER_PLACED => 'Order Placed',
            self::TYPE_ORDER_ACCEPTED => 'Order Accepted',
            self::TYPE_ORDER_PROCESSING => 'Order Processing',
            self::TYPE_ORDER_PACKED => 'Order Packed',
            self::TYPE_ORDER_READY_FOR_PICKUP => 'Order Ready for Pickup',
            self::TYPE_ORDER_DISPATCHED => 'Order Dispatched',
            self::TYPE_ORDER_RIDER_ARRIVED => 'Rider Arrived',
            self::TYPE_ORDER_DELIVERED => 'Order Delivered',
            self::TYPE_ORDER_CANCELLED => 'Order Cancelled',
            self::TYPE_ORDER_REFUNDED => 'Refund Processed',
        ];

        $messages = [
            self::TYPE_ORDER_PLACED => 'Your order #'.$order->order_number.' has been placed successfully.',
            self::TYPE_ORDER_ACCEPTED => 'Your order #'.$order->order_number.' has been accepted by the vendor.',
            self::TYPE_ORDER_PROCESSING => 'Your order #'.$order->order_number.' is being prepared.',
            self::TYPE_ORDER_PACKED => 'Your order #'.$order->order_number.' has been packed and is ready for dispatch.',
            self::TYPE_ORDER_READY_FOR_PICKUP => 'Your order #'.$order->order_number.' is ready for pickup at the station.',
            self::TYPE_ORDER_DISPATCHED => 'Your order #'.$order->order_number.' has been dispatched and is on the way.',
            self::TYPE_ORDER_RIDER_ARRIVED => 'Your rider has arrived for order #'.$order->order_number.'.',
            self::TYPE_ORDER_DELIVERED => 'Your order #'.$order->order_number.' has been delivered.',
            self::TYPE_ORDER_CANCELLED => 'Your order #'.$order->order_number.' has been cancelled.',
            self::TYPE_ORDER_REFUNDED => 'Your refund for order #'.$order->order_number.' has been processed.',
        ];

        $this->notifyCustomer($customer, $type, [
            'title' => $titles[$type] ?? 'Order Update',
            'message' => $messages[$type] ?? 'Your order status has been updated.',
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $newStatus,
            'previous_status' => $oldStatus,
            'url' => route('customer.orders.show', $order->id),
        ]);
    }

    public function notifyLogisticsAdmin(LogisticsAdmin $admin, string $type, array $data): Notification
    {
        return Notification::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => $type,
            'notifiable_type' => LogisticsAdmin::class,
            'notifiable_id' => $admin->id,
            'data' => $data,
        ]);
    }

    public function notifyVendorNewOrder(Order $order): void
    {
        $vendor = $order->vendor;
        if (! $vendor) {
            return;
        }

        $this->notifyVendor($vendor, self::TYPE_ORDER_PLACED, [
            'title' => 'New Order Received',
            'message' => 'You have received a new order #'.$order->order_number.'.',
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'customer_name' => $order->customer?->name,
            'total_amount' => $order->total_amount,
            'currency' => $order->currency,
            'url' => route('vendor.orders.show', $order->id),
        ]);
    }

    public function getUnreadCount(Customer $customer): int
    {
        return Notification::forUser($customer->id, 'customer')
            ->unread()
            ->count();
    }

    public function getVendorUnreadCount(Vendor $vendor): int
    {
        return Notification::forUser($vendor->id, 'vendor')
            ->unread()
            ->count();
    }

    public function getRecentForCustomer(Customer $customer, int $limit = 20): Collection
    {
        return Notification::forUser($customer->id, 'customer')
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function getRecentForVendor(Vendor $vendor, int $limit = 20): Collection
    {
        return Notification::forUser($vendor->id, 'vendor')
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function markAsRead(Collection $notifications): int
    {
        return $notifications->whereNull('read_at')
            ->each(fn ($n) => $n->update(['read_at' => now()]))
            ->count();
    }

    public function markAllAsReadForCustomer(Customer $customer): int
    {
        return Notification::forUser($customer->id, 'customer')
            ->unread()
            ->update(['read_at' => now()]);
    }

    public function markAllAsReadForVendor(Vendor $vendor): int
    {
        return Notification::forUser($vendor->id, 'vendor')
            ->unread()
            ->update(['read_at' => now()]);
    }

    public function broadcastToCustomers(string $title, string $message, ?string $url = null): void
    {
        Customer::chunkById(100, function ($customers) use ($title, $message, $url) {
            foreach ($customers as $customer) {
                $this->notifyCustomer($customer, self::TYPE_SYSTEM, [
                    'title' => $title,
                    'message' => $message,
                    'url' => $url,
                ]);
            }
        });
    }

    public function sendPromotion(Customer $customer, string $title, string $message, ?string $url = null): Notification
    {
        return $this->notifyCustomer($customer, self::TYPE_PROMOTION, [
            'title' => $title,
            'message' => $message,
            'url' => $url,
        ]);
    }
}
