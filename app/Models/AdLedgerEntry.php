<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only advertising ledger. Rows are never updated or deleted; mistakes
 * are corrected with compensating entries.
 */
class AdLedgerEntry extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'account_id', 'vendor_id', 'campaign_id', 'type', 'direction',
        'amount_minor', 'currency', 'balance_after_minor', 'reserved_after_minor',
        'reference', 'actor_type', 'actor_id', 'meta', 'created_at',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'balance_after_minor' => 'integer',
        'reserved_after_minor' => 'integer',
        'actor_id' => 'integer',
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public function account()
    {
        return $this->belongsTo(AdAccount::class, 'account_id');
    }

    public function campaign()
    {
        return $this->belongsTo(AdCampaign::class, 'campaign_id');
    }
}
