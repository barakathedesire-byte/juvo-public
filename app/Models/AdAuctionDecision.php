<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdAuctionDecision extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'request_uid', 'placement_key', 'placement_id', 'context_hash',
        'algorithm_version', 'candidate_count', 'winner_campaign_id',
        'winners', 'candidates', 'created_at',
    ];

    protected $casts = [
        'placement_id' => 'integer',
        'candidate_count' => 'integer',
        'winner_campaign_id' => 'integer',
        'winners' => 'array',
        'candidates' => 'array',
        'created_at' => 'datetime',
    ];

    public function winner()
    {
        return $this->belongsTo(AdCampaign::class, 'winner_campaign_id');
    }
}
