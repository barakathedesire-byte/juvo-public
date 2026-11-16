<?php

namespace App\Notifications;

use App\Models\AdCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdCampaignStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        public AdCampaign $campaign,
        public string $event,
        public ?string $note = null
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'campaign_id' => $this->campaign->id,
            'title' => $this->campaign->title,
            'event' => $this->event,
            'status' => $this->campaign->status,
            'note' => $this->note,
            'message' => $this->message(),
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your ad campaign: '.$this->campaign->title)
            ->line($this->message())
            ->line('Current status: '.$this->campaign->statusLabel().'.')
            ->when($this->note, fn ($mail) => $mail->line('Note: '.$this->note))
            ->action('View campaigns', url('/vendor/ads'));
    }

    protected function message(): string
    {
        return match ($this->event) {
            'approved' => 'Your ad campaign was approved.',
            'activated' => 'Your ad campaign is now live.',
            'rejected' => 'Your ad campaign was rejected.',
            'changes_requested' => 'Changes are required on your ad campaign.',
            'completed' => 'Your ad campaign has completed.',
            default => 'Your ad campaign status changed.',
        };
    }
}
