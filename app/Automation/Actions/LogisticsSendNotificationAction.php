<?php

namespace App\Automation\Actions;

use App\Automation\AutomationAction;
use App\Models\AutomationRun;
use App\Services\Logistics\LogisticsNotificationService;

class LogisticsSendNotificationAction implements AutomationAction
{
    public function __construct(protected LogisticsNotificationService $notifications) {}

    public function key(): string { return 'logistics.send_notification'; }
    public function label(): string { return 'Send logistics notification'; }
    public function mutates(): bool { return true; }

    public function handle(array $input, AutomationRun $run): array
    {
        $delivery = $this->notifications->send(
            (string) ($input['recipient_type'] ?? 'logistics_admin'),
            (int) ($input['recipient_id'] ?? 0),
            (string) ($input['channel'] ?? 'in_app'),
            (string) ($input['category'] ?? 'system'),
            (string) ($input['subject'] ?? 'Logistics notification'),
            (string) ($input['body'] ?? ''),
            (array) ($input['data'] ?? []),
            $input['dedup_key'] ?? null
        );

        return ['delivery_id' => $delivery->id, 'status' => $delivery->status];
    }
}
