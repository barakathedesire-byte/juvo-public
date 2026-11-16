<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A funding-aware, lifecycle-aware promotion rule.
 *
 * Existing rows keep working: `active` + `starts_at`/`ends_at` are still
 * honoured, and `status` defaults to `active`. `funding_source` states who
 * pays for the discount: the vendor, Juvo, or a joint split recorded in meta.
 */
class Promotion extends Model
{
    public const STATUSES = ['draft', 'pending', 'scheduled', 'active', 'paused', 'exhausted', 'expired', 'rejected', 'archived'];

    public const FUNDING_VENDOR = 'vendor';

    public const FUNDING_JUVO = 'juvo';

    public const FUNDING_JOINT = 'joint';

    protected $fillable = [
        'vendor_id', 'product_id', 'category_id', 'title', 'type', 'value', 'active',
        'starts_at', 'ends_at', 'status', 'scope', 'funding_source', 'stack_group',
        'priority', 'budget_amount', 'budget_spent', 'per_order_cap', 'coupon_compatible',
        'price_floor', 'created_by', 'approved_by', 'approved_at', 'rejection_reason', 'meta',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'budget_amount' => 'decimal:2',
        'budget_spent' => 'decimal:2',
        'per_order_cap' => 'decimal:2',
        'coupon_compatible' => 'boolean',
        'price_floor' => 'decimal:2',
        'approved_at' => 'datetime',
        'meta' => 'array',
    ];

    public function isRunning(): bool
    {
        return $this->hasStarted() && ! $this->isEnded() && $this->isRunnableStatus();
    }

    public function isRunnableStatus(): bool
    {
        if ($this->status !== null && $this->status !== 'active') {
            return false;
        }

        // Legacy rows may predate `status`; fall back to `active`.
        return (bool) $this->active || $this->status === 'active';
    }

    public function hasStarted(): bool
    {
        return ! $this->starts_at || $this->starts_at->lte(now());
    }

    public function isEnded(): bool
    {
        return $this->ends_at !== null && $this->ends_at->lt(now());
    }

    /**
     * Remaining spendable budget, or null when the promotion is uncapped.
     */
    public function remainingBudget(): ?float
    {
        if ($this->budget_amount === null) {
            return null;
        }

        return round(max(0, (float) $this->budget_amount - (float) $this->budget_spent), 2);
    }

    public function isExhausted(): bool
    {
        return $this->status === 'exhausted'
            || ($this->budget_amount !== null && (float) $this->budget_spent >= (float) $this->budget_amount);
    }

    public function fundsByJuvo(): bool
    {
        return in_array($this->funding_source, [self::FUNDING_JUVO, self::FUNDING_JOINT], true);
    }

    /**
     * Split a discount amount across vendor / juvo according to funding source.
     *
     * @return array{vendor:float,juvo:float}
     */
    public function splitFunding(float $amount): array
    {
        $amount = round($amount, 2);

        return match ($this->funding_source) {
            self::FUNDING_JUVO => ['vendor' => 0.0, 'juvo' => $amount],
            self::FUNDING_JOINT => (function () use ($amount) {
                $juvoShare = (float) ($this->meta['juvo_share'] ?? 0.5);
                $juvo = round($amount * max(0, min(1, $juvo)), 2);

                return ['vendor' => round($amount - $juvo, 2), 'juvo' => $juvo];
            })(),
            default => ['vendor' => $amount, 'juvo' => 0.0],
        };
    }

    public function scopeRunning($query)
    {
        $now = now();

        return $query->where('active', true)
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', 'active'))
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function reservations()
    {
        return $this->hasMany(PromotionReservation::class);
    }

    public function redemptions()
    {
        return $this->hasMany(PromotionRedemption::class);
    }

    /**
     * Human-readable explanation shown to customers.
     */
    public function explain(float $amount): string
    {
        $label = $this->title ?: ('Promotion #'.$this->id);
        $type = $this->type === 'percent' ? ((float) $this->value).'% off' : 'discount';

        return sprintf('%s — %s %s', $label, $this->type === 'percent' ? $type : $this->formatMoney($amount), $this->funding_source === self::FUNDING_JUVO ? '(Juvo-funded)' : '');
    }

    protected function formatMoney(float $amount): string
    {
        return number_format(round($amount, 2), 2);
    }
}
