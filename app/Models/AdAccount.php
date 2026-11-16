<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdAccount extends Model
{
    public const STATUSES = ['active', 'restricted', 'suspended'];

    protected $fillable = [
        'vendor_id', 'currency', 'status',
        'credit_limit_minor', 'funded_minor', 'spent_minor', 'reserved_minor',
        'auto_bid_enabled', 'auto_bid_strategy', 'max_cpc_minor', 'target_cpa_minor',
        'target_roas', 'daily_spend_cap_minor',
    ];

    protected $casts = [
        'credit_limit_minor' => 'integer',
        'funded_minor' => 'integer',
        'spent_minor' => 'integer',
        'reserved_minor' => 'integer',
        'auto_bid_enabled' => 'boolean',
        'max_cpc_minor' => 'integer',
        'target_cpa_minor' => 'integer',
        'target_roas' => 'float',
        'daily_spend_cap_minor' => 'integer',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function ledgerEntries()
    {
        return $this->hasMany(AdLedgerEntry::class, 'account_id');
    }

    /** Available = funded + credit line - spent - reserved. */
    public function getAvailableMinorAttribute(): int
    {
        return ($this->funded_minor + $this->credit_limit_minor)
            - $this->spent_minor
            - $this->reserved_minor;
    }

    public function getBalanceMinorAttribute(): int
    {
        return $this->funded_minor - $this->spent_minor;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
