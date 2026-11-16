<?php

namespace App\Notifications;

use App\Models\Vendor;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VendorVerificationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Vendor $vendor,
        public string $event,
        public ?string $note = null
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Your store: '.$this->subject())
            ->greeting('Hello '.($notifiable->name ?? 'there').',')
            ->line($this->message());

        if ($this->note) {
            $mail->line('Note: '.$this->note);
        }

        return $mail->action('Go to your dashboard', url('/vendor/dashboard'));
    }

    public function toArray($notifiable): array
    {
        return [
            'vendor_id' => $this->vendor->id,
            'event' => $this->event,
            'note' => $this->note,
            'message' => $this->message(),
        ];
    }

    protected function subject(): string
    {
        return match ($this->event) {
            'verified' => 'your store is verified',
            'rejected' => 'verification update',
            'suspended' => 'your store has been suspended',
            'reinstated' => 'your store has been reinstated',
            default => 'status update',
        };
    }

    protected function message(): string
    {
        return match ($this->event) {
            'verified' => 'Your store has been verified and is now live.',
            'rejected' => 'Your verification could not be approved. Please review the notes and resubmit.',
            'suspended' => 'Your store has been suspended.',
            'reinstated' => 'Your store has been reinstated.',
            default => 'Your store status changed.',
        };
    }
}
