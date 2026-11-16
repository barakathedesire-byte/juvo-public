<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminNewOrderNotification extends Notification
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
            ->subject('New order #'.$order->id.' on the marketplace')
            ->greeting('Hello '.($notifiable->name ?? 'there').',')
            ->line('A new order #'.$order->id.' was placed.')
            ->line('Total: '.number_format((float) $order->total_amount, 2).' '.$order->currency)
            ->line('Payment: '.$order->payment_status.' via '.($order->payment_method ?? 'n/a'))
            ->action('Open in admin', url('/admin/orders/'.$order->id));
    }

    public function toArray($notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'message' => 'New order #'.$this->order->id.' placed.',
            'total' => (float) $this->order->total_amount,
            'currency' => $this->order->currency,
        ];
    }
}
