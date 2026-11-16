<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdDailyStat extends Model
{
    protected $fillable = [
        'campaign_id', 'date', 'impressions', 'clicks', 'conversions', 'spend', 'spend_minor', 'currency',
    ];

    protected $casts = [
        'date' => 'date',
        'impressions' => 'integer',
        'clicks' => 'integer',
        'conversions' => 'integer',
        'spend' => 'float',
        'spend_minor' => 'integer',
    ];

    public function campaign()
    {
        return $this->belongsTo(AdCampaign::class, 'campaign_id');
    }
}
