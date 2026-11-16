<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'vendor_id', 'title', 'discount', 'type', 'description', 'min_order_value', 'max_discount',
        'usage_limit', 'used_count', 'per_customer_limit', 'starts_at', 'expires_at', 'is_active',
    ];

    protected $casts = [
        'discount' => 'decimal:2',
        'min_order_value' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'used_count' => 'integer',
        'usage_limit' => 'integer',
        'per_customer_limit' => 'integer',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at && Carbon::parse($this->expires_at)->isPast();
    }

    public function hasStarted(): bool
    {
        return ! $this->starts_at || Carbon::parse($this->starts_at)->isPast();
    }

    public function isExhausted(): bool
    {
        return $this->usage_limit !== null && $this->used_count >= $this->usage_limit;
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active && $this->hasStarted() && ! $this->isExpired() && ! $this->isExhausted();
    }

    /**
     * Human-readable reason the coupon cannot be used, or null when usable.
     */
    public function rejectionReason(?float $subtotal = null): ?string
    {
        if (! $this->is_active) {
            return 'This coupon is not active.';
        }
        if (! $this->hasStarted()) {
            return 'This coupon is not valid yet.';
        }
        if ($this->isExpired()) {
            return 'This coupon has expired.';
        }
        if ($this->isExhausted()) {
            return 'This coupon has reached its usage limit.';
        }
        if ($subtotal !== null && $this->min_order_value > 0 && $subtotal < (float) $this->min_order_value) {
            return 'This coupon requires a minimum order value.';
        }

        return null;
    }

    /**
     * Discount amount for a given subtotal, honouring the max_discount cap.
     */
    public function discountFor(float $subtotal): float
    {
        $discount = $this->type === 'percentage'
            ? $subtotal * ((float) $this->discount / 100)
            : (float) $this->discount;

        if ($this->max_discount !== null && $discount > (float) $this->max_discount) {
            $discount = (float) $this->max_discount;
        }

        return round(min($discount, $subtotal), 2);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function isVendorScoped(): bool
    {
        return $this->vendor_id !== null;
    }

    public function scopeUsable($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            })
            ->where(function ($q) {
                $q->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit');
            });
    }
}
