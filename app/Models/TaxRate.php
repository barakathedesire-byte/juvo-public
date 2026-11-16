<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxRate extends Model
{
    protected $fillable = ['name', 'rate', 'country', 'region', 'is_active'];

    protected $casts = [
        'rate' => 'decimal:3',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Resolve the most specific active rate for a region/country,
     * falling back to a global rate (no region/country).
     */
    public static function resolve(?string $region = null, ?string $country = null): ?self
    {
        return static::active()
            ->where(function ($q) use ($region, $country) {
                $q->where(function ($inner) {
                    $inner->whereNull('region')->whereNull('country');
                });
                if ($region) {
                    $q->orWhere(fn ($inner) => $inner->whereNull('country')->where('region', $region));
                    $q->orWhere('region', $region);
                }
                if ($country) {
                    $q->orWhere(fn ($inner) => $inner->whereNull('region')->where('country', $country));
                }
            })
            ->orderByRaw('country is not null desc, region is not null desc')
            ->first();
    }
}
