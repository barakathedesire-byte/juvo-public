<?php

namespace App\Models;

use App\Models\Logistics\LogisticsLocationSetting;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $fillable = [
        'region_id', 'name', 'postal_code', 'shipping_fee', 'pickup_fee', 'free_over',
        'eta_label', 'pickup_eta', 'door_eta', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'shipping_fee' => 'decimal:2',
        'pickup_fee' => 'decimal:2',
        'free_over' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function logisticsSettings()
    {
        return $this->hasOne(LogisticsLocationSetting::class, 'location_id');
    }

    public function isDeliveryCovered(): bool
    {
        return (bool) ($this->is_active && ($this->logisticsSettings?->is_covered ?? true));
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
