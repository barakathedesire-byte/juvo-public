<?php

namespace App\Models\Logistics;

use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    protected $table = 'notification_templates';

    protected $fillable = ['name', 'key', 'channel', 'subject', 'body', 'variables', 'is_active', 'version'];
    protected $casts = ['variables' => 'array', 'is_active' => 'boolean', 'version' => 'integer'];
}
