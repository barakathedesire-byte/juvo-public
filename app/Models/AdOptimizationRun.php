<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdOptimizationRun extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'campaign_id', 'algorithm_version', 'strategy',
        'before_bid_minor', 'after_bid_minor', 'metrics', 'status', 'reason', 'created_at',
    ];

    protected $casts = [
        'before_bid_minor' => 'integer',
        'after_bid_minor' => 'integer',
        'metrics' => 'array',
        'created_at' => 'datetime',
    ];

    public function campaign()
    {
        return $this->belongsTo(AdCampaign::class, 'campaign_id');
    }
}
