<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingRate extends Model
{
    protected $fillable = [
        'name', 'country', 'region', 'min_order_value', 'max_order_value',
        'rate', 'free_over', 'eta_label', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'min_order_value' => 'decimal:2',
        'max_order_value' => 'decimal:2',
        'rate' => 'decimal:2',
        'free_over' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Pick the best active rate for a region + subtotal. Region-specific rules
     * win over global ones; within a tier the cheapest match is used.
     */
    public static function resolve(?string $region = null, float $subtotal = 0, ?string $country = null): ?self
    {
        $candidates = static::active()
            ->where('min_order_value', '<=', $subtotal)
            ->where(function ($q) use ($subtotal) {
                $q->whereNull('max_order_value')->orWhere('max_order_value', '>=', $subtotal);
            })
            ->where(function ($q) use ($region, $country) {
                $q->where(function ($inner) {
                    $inner->whereNull('region')->whereNull('country');
                });
                if ($region) {
                    $q->orWhere('region', $region);
                }
                if ($country) {
                    $q->orWhere('country', $country);
                }
            })
            ->orderByRaw('region is not null desc, country is not null desc')
            ->orderBy('sort_order')
            ->orderBy('rate')
            ->get();

        return $candidates->first();
    }

    /**
     * Effective shipping cost for a subtotal, applying the free-over threshold.
     */
    public function costFor(float $subtotal): float
    {
        if ($this->free_over !== null && $subtotal >= (float) $this->free_over) {
            return 0.0;
        }

        return (float) $this->rate;
    }
}
