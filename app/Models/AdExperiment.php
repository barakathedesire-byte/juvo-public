<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdExperiment extends Model
{
    public const STATUSES = ['running', 'stopped'];

    protected $fillable = [
        'campaign_id', 'key', 'variant', 'allocation', 'status', 'metrics', 'started_at', 'ended_at',
    ];

    protected $casts = [
        'allocation' => 'integer',
        'metrics' => 'array',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function campaign()
    {
        return $this->belongsTo(AdCampaign::class, 'campaign_id');
    }
}
