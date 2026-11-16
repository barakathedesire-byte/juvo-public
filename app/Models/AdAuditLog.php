<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_type', 'actor_id', 'action', 'campaign_id', 'account_id',
        'subject_type', 'subject_id', 'before', 'after', 'reason', 'ip', 'created_at',
    ];

    protected $casts = [
        'actor_id' => 'integer',
        'campaign_id' => 'integer',
        'account_id' => 'integer',
        'subject_id' => 'integer',
        'before' => 'array',
        'after' => 'array',
        'created_at' => 'datetime',
    ];
}
