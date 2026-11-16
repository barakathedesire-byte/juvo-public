<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdTargetingRule extends Model
{
    public $timestamps = false;

    protected $fillable = ['campaign_id', 'kind', 'dimension', 'operator', 'value', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public function campaign()
    {
        return $this->belongsTo(AdCampaign::class, 'campaign_id');
    }
}
