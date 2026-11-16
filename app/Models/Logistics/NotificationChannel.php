<?php

namespace App\Models\Logistics;

use Illuminate\Database\Eloquent\Model;

class NotificationChannel extends Model
{
    protected $table = 'notification_channels';

    protected $fillable = ['name', 'key', 'type', 'is_enabled', 'provider', 'config', 'health_status', 'last_checked_at'];
    protected $casts = ['is_enabled' => 'boolean', 'config' => 'array', 'last_checked_at' => 'datetime'];
}
