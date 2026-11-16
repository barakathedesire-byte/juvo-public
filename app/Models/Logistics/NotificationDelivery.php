<?php

namespace App\Models\Logistics;

use Illuminate\Database\Eloquent\Model;

class NotificationDelivery extends Model
{
    protected $table = 'notification_deliveries';

    protected $fillable = [
        'notification_id', 'recipient_type', 'recipient_id', 'channel', 'category', 'status',
        'dedup_key', 'provider_response', 'error_message', 'queued_at', 'sent_at',
        'delivered_at', 'failed_at', 'read_at',
    ];
    protected $casts = [
        'queued_at' => 'datetime', 'sent_at' => 'datetime', 'delivered_at' => 'datetime',
        'failed_at' => 'datetime', 'read_at' => 'datetime',
    ];
}
