<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdCampaign extends Model
{
    /* Lifecycle statuses */
    public const STATUSES = [
        'draft', 'submitted', 'under_review', 'changes_requested',
        'rejected', 'approved', 'awaiting_payment', 'scheduled',
        'active', 'paused', 'completed', 'cancelled', 'failed',
    ];

    public const BID_TYPES = ['manual_cpc', 'auto_cpc', 'target_cpa', 'target_roas', 'cpm', 'flat'];

    protected $fillable = [
        'vendor_id', 'product_id', 'placement_id', 'package_id',
        'title', 'objective', 'billing_model',
        'currency', 'bid_type', 'bid_minor', 'max_cpc_minor', 'quality_factor',
        'total_budget_minor', 'daily_budget_minor', 'amount_paid_minor', 'spent_minor', 'reserved_minor',
        'target_cpa_minor', 'target_roas', 'auto_bid_enabled', 'pacing', 'attribution_window_days',
        'creative_headline', 'creative_body', 'creative_image', 'target_url',
        'total_budget', 'daily_budget', 'amount_paid', 'spent',
        'impressions', 'clicks', 'conversions',
        'payment_method', 'payment_meta', 'paid_at', 'start_date', 'end_date',
        'status', 'auto_approved', 'admin_note', 'priority',
        'submitted_at', 'reviewed_at', 'reviewed_by',
    ];

    protected $casts = [
        'amount_paid' => 'float',
        'total_budget' => 'float',
        'daily_budget' => 'float',
        'spent' => 'float',
        'payment_meta' => 'array',
        'currency' => 'string',
        'bid_type' => 'string',
        'bid_minor' => 'integer',
        'max_cpc_minor' => 'integer',
        'quality_factor' => 'float',
        'total_budget_minor' => 'integer',
        'daily_budget_minor' => 'integer',
        'amount_paid_minor' => 'integer',
        'spent_minor' => 'integer',
        'reserved_minor' => 'integer',
        'target_cpa_minor' => 'integer',
        'target_roas' => 'float',
        'auto_bid_enabled' => 'boolean',
        'pacing' => 'string',
        'attribution_window_days' => 'integer',
        'impressions' => 'integer',
        'clicks' => 'integer',
        'conversions' => 'integer',
        'auto_approved' => 'boolean',
        'priority' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'paid_at' => 'datetime',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function placement()
    {
        return $this->belongsTo(AdPlacement::class, 'placement_id');
    }

    public function package()
    {
        return $this->belongsTo(AdPackage::class, 'package_id');
    }

    public function creatives()
    {
        return $this->hasMany(AdCreative::class, 'campaign_id')->orderByDesc('version');
    }

    public function moderationLogs()
    {
        return $this->hasMany(AdModerationLog::class, 'campaign_id')->orderByDesc('id');
    }

    public function events()
    {
        return $this->hasMany(AdEvent::class, 'campaign_id');
    }

    public function dailyStats()
    {
        return $this->hasMany(AdDailyStat::class, 'campaign_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function account()
    {
        return $this->hasOne(AdAccount::class, 'vendor_id', 'vendor_id');
    }

    public function ledgerEntries()
    {
        return $this->hasMany(AdLedgerEntry::class, 'campaign_id');
    }

    public function reservations()
    {
        return $this->hasMany(AdBudgetReservation::class, 'campaign_id');
    }

    public function targetingRules()
    {
        return $this->hasMany(AdTargetingRule::class, 'campaign_id');
    }

    public function experiments()
    {
        return $this->hasMany(AdExperiment::class, 'campaign_id');
    }

    public function optimizationRuns()
    {
        return $this->hasMany(AdOptimizationRun::class, 'campaign_id');
    }

    public function attributionTouchpoints()
    {
        return $this->hasMany(AdAttributionTouchpoint::class, 'campaign_id');
    }

    public function policyReviews()
    {
        return $this->hasMany(AdPolicyReview::class, 'campaign_id');
    }

    public function spendCurrency(): string
    {
        return $this->currency ?: \App\Support\Ads\Money::DEFAULT_CURRENCY;
    }

    /** Budget still available to spend, in minor units. */
    public function availableMinor(): int
    {
        if ($this->amount_paid_minor > 0 || $this->total_budget_minor > 0) {
            return max(0, $this->amount_paid_minor - $this->spent_minor - $this->reserved_minor);
        }

        return max(0, \App\Support\Ads\Money::minor($this->amount_paid) - (int) $this->spent_minor);
    }

    public function lifetimeBudgetMinor(): int
    {
        return $this->total_budget_minor > 0
            ? $this->total_budget_minor
            : \App\Support\Ads\Money::minor($this->total_budget);
    }

    public function dailyBudgetMinorValue(): ?int
    {
        if ($this->daily_budget_minor !== null) {
            return $this->daily_budget_minor;
        }

        return $this->daily_budget !== null ? \App\Support\Ads\Money::minor($this->daily_budget) : null;
    }

    public function killSwitchOn(): bool
    {
        return $this->status === 'active' && $this->budgetRemainingMinor() <= 0;
    }

    public function budgetRemainingMinor(): int
    {
        return max(0, $this->amount_paid_minor - $this->spent_minor);
    }

    public function isLive(): bool
    {
        return $this->status === 'active'
            && (! $this->end_date || $this->end_date->isFuture());
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'changes_requested'], true);
    }

    public function getCtrAttribute(): float
    {
        return $this->impressions > 0 ? round($this->clicks / $this->impressions * 100, 2) : 0.0;
    }

    public function getCvrAttribute(): float
    {
        return $this->clicks > 0 ? round($this->conversions / $this->clicks * 100, 2) : 0.0;
    }

    public function getBudgetRemainingAttribute(): float
    {
        return max(0, (float) $this->amount_paid - (float) $this->spent);
    }

    public function getBudgetUsedPercentAttribute(): float
    {
        return $this->amount_paid > 0 ? round($this->spent / $this->amount_paid * 100, 1) : 0.0;
    }

    public function scopeStatus($query, $status)
    {
        return $status ? $query->where('status', $status) : $query;
    }

    public function scopePendingReview($query)
    {
        return $query->whereIn('status', ['submitted', 'under_review']);
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'active' => 'text-bg-success',
            'scheduled', 'approved' => 'text-bg-primary',
            'submitted', 'under_review', 'awaiting_payment' => 'text-bg-warning',
            'changes_requested' => 'text-bg-info',
            'rejected', 'failed', 'cancelled' => 'text-bg-danger',
            'paused', 'completed' => 'text-bg-secondary',
            default => 'text-bg-light',
        };
    }

    public function statusLabel(): string
    {
        return ucwords(str_replace('_', ' ', (string) $this->status));
    }
}
