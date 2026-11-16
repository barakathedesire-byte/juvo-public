<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdAttributionTouchpoint extends Model
{
    public const EVENTS = ['impression', 'click', 'conversion'];

    public const STATUSES = ['pending', 'attributed', 'expired', 'rejected'];

    protected $fillable = [
        'campaign_id', 'product_id', 'order_id', 'order_detail_id',
        'event_type', 'model', 'click_id', 'revenue_minor', 'currency',
        'status', 'reference', 'occurred_at', 'expires_at',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'order_id' => 'integer',
        'order_detail_id' => 'integer',
        'revenue_minor' => 'integer',
        'occurred_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function campaign()
    {
        return $this->belongsTo(AdCampaign::class, 'campaign_id');
    }
}
