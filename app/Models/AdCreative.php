<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdCreative extends Model
{
    protected $fillable = [
        'campaign_id', 'version', 'headline', 'body',
        'image', 'target_url', 'is_approved', 'note',
    ];

    protected $casts = [
        'version' => 'integer',
        'is_approved' => 'boolean',
    ];

    public function campaign()
    {
        return $this->belongsTo(AdCampaign::class, 'campaign_id');
    }
}
