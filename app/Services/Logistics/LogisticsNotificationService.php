<?php

namespace App\Services\Logistics;

use App\Models\Customer;
use App\Models\Logistics\LogisticsAdmin;
use App\Models\Logistics\NotificationChannel;
use App\Models\Logistics\NotificationDelivery;
use App\Models\Logistics\NotificationPreference;
use App\Models\Rider\Rider;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Mail;

class LogisticsNotificationService
{
    public function __construct(protected NotificationService $notifications) {}

    public function send(string $recipientType, int $recipientId, string $channel, string $category, string $subject, string $body, array $data = [], ?string $dedupKey = null): NotificationDelivery
    {
        if ($dedupKey) {
            $existing = NotificationDelivery::where('dedup_key', $dedupKey)->first();
            if ($existing) {
                return $existing;
            }
        }

        $preference = NotificationPreference::where('recipient_type', $recipientType)
            ->where('recipient_id', $recipientId)
            ->where('channel', $channel)
            ->where('category', $category)
            ->first();
        if ($preference && ! $preference->is_enabled) {
            return $this->record($recipientType, $recipientId, $channel, $category, 'skipped', $dedupKey, 'Recipient preference disabled.');
        }

        $channelModel = NotificationChannel::where('key', $channel)->first();
        if (! $channelModel || ! $channelModel->is_enabled) {
            return $this->record($recipientType, $recipientId, $channel, $category, 'skipped', $dedupKey, 'Channel disabled.');
        }

        $delivery = $this->record($recipientType, $recipientId, $channel, $category, 'queued', $dedupKey);
        $delivery->update(['queued_at' => now()]);

        try {
            if ($channel === 'in_app') {
                $this->sendInApp($recipientType, $recipientId, $subject, $body, $data);
                $delivery->update(['status' => 'delivered', 'sent_at' => now(), 'delivered_at' => now(), 'provider_response' => 'Stored as in-app notification.']);
            } elseif ($channel === 'email') {
                $email = $this->recipientEmail($recipientType, $recipientId);
                if (! $email) {
                    throw new \RuntimeException('Recipient has no email address.');
                }
                Mail::raw($body, fn ($message) => $message->to($email)->subject($subject));
                $delivery->update(['status' => 'sent', 'sent_at' => now(), 'provider_response' => 'Accepted by mail transport.']);
            } else {
                throw new \RuntimeException('Provider not configured for channel: '.$channel);
            }
        } catch (\Throwable $e) {
            $delivery->update(['status' => 'failed', 'failed_at' => now(), 'error_message' => $e->getMessage()]);
        }

        return $delivery->fresh();
    }

    protected function sendInApp(string $recipientType, int $recipientId, string $subject, string $body, array $data): void
    {
        $payload = array_merge($data, ['title' => $subject, 'message' => $body]);

        match ($recipientType) {
            'customer' => $this->notifications->notifyCustomer(Customer::findOrFail($recipientId), 'system', $payload),
            'vendor' => $this->notifications->notifyVendor(Vendor::findOrFail($recipientId), 'system', $payload),
            'logistics_admin' => $this->notifications->notifyLogisticsAdmin(LogisticsAdmin::findOrFail($recipientId), 'system', $payload),
            default => $this->notifications->notifyAdmin(User::findOrFail($recipientId), 'system', $payload),
        };
    }

    protected function recipientEmail(string $recipientType, int $recipientId): ?string
    {
        return match ($recipientType) {
            'customer' => Customer::find($recipientId)?->email,
            'vendor' => Vendor::find($recipientId)?->email,
            'rider' => Rider::find($recipientId)?->email,
            'logistics_admin' => LogisticsAdmin::find($recipientId)?->email,
            default => User::find($recipientId)?->email,
        };
    }

    protected function record(string $recipientType, int $recipientId, string $channel, string $category, string $status, ?string $dedupKey, ?string $error = null): NotificationDelivery
    {
        return NotificationDelivery::create([
            'recipient_type' => $recipientType,
            'recipient_id' => $recipientId,
            'channel' => $channel,
            'category' => $category,
            'status' => $status,
            'dedup_key' => $dedupKey,
            'error_message' => $error,
            'queued_at' => now(),
            'failed_at' => $status === 'failed' ? now() : null,
        ]);
    }
}
