<?php

namespace App\Models\Logistics;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    protected $table = 'notification_preferences';

    protected $fillable = ['recipient_type', 'recipient_id', 'channel', 'category', 'is_enabled'];
    protected $casts = ['is_enabled' => 'boolean'];
}
