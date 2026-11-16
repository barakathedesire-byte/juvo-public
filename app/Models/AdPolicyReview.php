<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdPolicyReview extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'campaign_id', 'creative_id', 'reviewer_id', 'decision', 'reason_code', 'note', 'created_at',
    ];

    protected $casts = [
        'creative_id' => 'integer',
        'reviewer_id' => 'integer',
        'created_at' => 'datetime',
    ];

    public function campaign()
    {
        return $this->belongsTo(AdCampaign::class, 'campaign_id');
    }
}
