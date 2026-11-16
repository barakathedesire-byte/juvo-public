<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdBudgetReservation extends Model
{
    public const STATUSES = ['held', 'committed', 'released'];

    protected $fillable = [
        'campaign_id', 'account_id', 'period', 'period_key',
        'amount_minor', 'currency', 'status', 'reference', 'expires_at',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'expires_at' => 'datetime',
    ];

    public function campaign()
    {
        return $this->belongsTo(AdCampaign::class, 'campaign_id');
    }

    public function account()
    {
        return $this->belongsTo(AdAccount::class, 'account_id');
    }
}
