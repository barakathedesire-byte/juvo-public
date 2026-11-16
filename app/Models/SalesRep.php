<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesRep extends Model
{
    protected $fillable = [
        'user_id', 'code', 'name', 'email', 'phone', 'national_id', 'address', 'next_of_kin_name', 'next_of_kin_phone', 'region_id', 'location_id',
        'commission_rate', 'monthly_target', 'is_active',
        'status', 'verification_status', 'sponsor_id',
        'payout_method', 'payout_account_name', 'payout_account_number', 'payout_status',
        'terms_version', 'terms_accepted_at',
        'applied_at', 'approved_at', 'approved_by', 'rejected_at', 'rejection_reason',
        'suspended_at', 'suspension_reason',
        'customer_confirmation_required', 'currency', 'notes',
    ];

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'monthly_target' => 'decimal:2',
        'is_active' => 'boolean',
        'customer_confirmation_required' => 'boolean',
        'payout_account_number' => 'encrypted',
        'terms_accepted_at' => 'datetime',
        'applied_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'suspended_at' => 'datetime',
    ];

    protected $hidden = [
        'payout_account_number',
    ];

    public const STATUSES = [
        'draft', 'applied', 'verification', 'review', 'approved', 'active',
        'suspended', 'rejected', 'closed',
    ];

    public const OPERATIONAL_STATUSES = ['approved', 'active'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function sponsor()
    {
        return $this->belongsTo(SalesRep::class, 'sponsor_id');
    }

    public function sponsees()
    {
        return $this->hasMany(SalesRep::class, 'sponsor_id');
    }

    public function leads()
    {
        return $this->hasMany(SalesLead::class);
    }

    public function attributions()
    {
        return $this->hasMany(SalesAttribution::class);
    }

    public function commissionEntries()
    {
        return $this->hasMany(AgentCommissionEntry::class, 'agent_id');
    }

    public function payoutRequests()
    {
        return $this->hasMany(AgentPayoutRequest::class, 'agent_id');
    }

    public function rankHistories()
    {
        return $this->hasMany(AgentRankHistory::class, 'agent_id')->latest('id');
    }

    public function fraudReviews()
    {
        return $this->hasMany(AgentFraudReview::class, 'agent_id');
    }

    public function statusHistories()
    {
        return $this->hasMany(AgentStatusHistory::class, 'agent_id')->latest('id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeOperational($query)
    {
        return $query->where('is_active', true)
            ->whereIn('status', self::OPERATIONAL_STATUSES)
            ->where('verification_status', 'verified');
    }

    public static function forCode(?string $code): ?self
    {
        if (! $code) {
            return null;
        }

        return static::active()->where('code', $code)->first();
    }

    public function isApproved(): bool
    {
        return in_array($this->status, self::OPERATIONAL_STATUSES, true);
    }

    public function canOperate(): bool
    {
        return $this->is_active && $this->isApproved() && $this->verification_status === 'verified';
    }

    public function currentRank(): ?AgentRankDefinition
    {
        return $this->rankHistories()->with('rank')->first()?->rank;
    }

    public function commissionFor(float $orderTotal): float
    {
        return round($orderTotal * ((float) $this->commission_rate / 100), 2);
    }
}
