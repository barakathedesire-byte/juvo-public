<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Order $order,
        public string $from,
        public string $to,
        public ?string $reason = null
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Order #'.$this->order->id.' — '.$this->statusLabel($this->to))
            ->greeting('Hello '.($notifiable->name ?? 'there').',')
            ->line('Your order #'.$this->order->id.' is now '.$this->statusLabel($this->to).'.')
            ->line('Status changed from '.$this->statusLabel($this->from).' to '.$this->statusLabel($this->to).'.');

        if ($this->reason) {
            $mail->line('Note: '.$this->reason);
        }

        return $mail->action('View your order', url('/customer/profile'));
    }

    public function toArray($notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'from' => $this->from,
            'to' => $this->to,
            'status' => $this->to,
            'reason' => $this->reason,
            'message' => 'Order #'.$this->order->id.' is now '.$this->statusLabel($this->to).'.',
        ];
    }

    protected function statusLabel(string $status): string
    {
        return Order::STATUS_LABELS[$status] ?? ucwords(str_replace('_', ' ', $status));
    }
}
