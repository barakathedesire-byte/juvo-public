<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderPlacedNotification extends Notification
{
    use Queueable;

    public function __construct(public Order $order) {}

    public function via($notifiable): array
    {
        return $notifiable instanceof \App\Models\Customer ? ['mail', 'database'] : ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $order = $this->order;

        $mail = (new MailMessage)
            ->subject('Order #'.$order->id.' confirmed — thank you!')
            ->greeting('Hello '.($notifiable->name ?? 'there').',')
            ->line('We have received your order #'.$order->id.'.')
            ->line('Items total: '.number_format((float) $order->subtotal, 2).' '.$order->currency);

        if ((float) $order->discount_amount > 0) {
            $mail->line('Discount'.($order->coupon_code ? ' ('.$order->coupon_code.')' : '').': -'.number_format((float) $order->discount_amount, 2).' '.$order->currency);
        }
        if ((float) $order->delivery_fee > 0) {
            $mail->line('Shipping: '.number_format((float) $order->delivery_fee, 2).' '.$order->currency);
        }
        if ((float) $order->tax_amount > 0) {
            $mail->line('Tax: '.number_format((float) $order->tax_amount, 2).' '.$order->currency);
        }

        return $mail
            ->line('Total: '.number_format((float) $order->total_amount, 2).' '.$order->currency)
            ->action('View your order', url('/customer/orders'));
    }

    public function toArray($notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'message' => 'Order #'.$this->order->id.' placed successfully.',
            'total' => (float) $this->order->total_amount,
            'currency' => $this->order->currency,
        ];
    }
}
