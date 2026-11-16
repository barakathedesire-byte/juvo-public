<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VendorNewOrderNotification extends Notification
{
    use Queueable;

    public function __construct(public Order $order) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $order = $this->order;

        return (new MailMessage)
            ->subject('New order #'.$order->id.' received')
            ->greeting('Hello '.($notifiable->name ?? 'there').',')
            ->line('You have a new order #'.$order->id.'.')
            ->line('Order total: '.number_format((float) $order->total_amount, 2).' '.$order->currency)
            ->line('Your net amount: '.number_format((float) $order->net_vendor_amount, 2).' '.$order->currency)
            ->action('Open order', url('/vendor/orders'));
    }

    public function toArray($notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'message' => 'New order #'.$this->order->id.' received.',
            'total' => (float) $this->order->total_amount,
            'currency' => $this->order->currency,
        ];
    }
}
