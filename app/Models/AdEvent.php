<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'campaign_id', 'placement_id', 'type', 'event_uid', 'decision_id',
        'spend_minor', 'currency', 'is_billable', 'is_valid', 'invalid_reason',
        'ip', 'user_agent', 'meta', 'created_at',
    ];

    protected $casts = [
        'decision_id' => 'integer',
        'spend_minor' => 'integer',
        'is_billable' => 'boolean',
        'is_valid' => 'boolean',
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public function campaign()
    {
        return $this->belongsTo(AdCampaign::class, 'campaign_id');
    }
}
