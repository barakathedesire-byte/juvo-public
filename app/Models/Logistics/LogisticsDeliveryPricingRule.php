<?php

namespace App\Models\Logistics;

use App\Models\Location;
use Illuminate\Database\Eloquent\Model;

class LogisticsDeliveryPricingRule extends Model
{
    protected $table = 'logistics_delivery_pricing_rules';

    protected $fillable = [
        'zone_id', 'location_id', 'name', 'delivery_type', 'vehicle_type',
        'base_fee', 'per_km_fee', 'minimum_fee', 'peak_surcharge',
        'handling_surcharge', 'free_delivery_threshold', 'distance_tiers',
        'effective_from', 'effective_to', 'priority', 'is_active', 'status',
        'created_by', 'approved_by', 'approved_at', 'effective_at', 'version',
        'demand_adjustment_enabled', 'capacity_adjustment_enabled', 'max_adjustment_percent',
    ];

    protected $casts = [
        'base_fee' => 'decimal:2',
        'per_km_fee' => 'decimal:2',
        'minimum_fee' => 'decimal:2',
        'peak_surcharge' => 'decimal:2',
        'handling_surcharge' => 'decimal:2',
        'free_delivery_threshold' => 'decimal:2',
        'max_adjustment_percent' => 'decimal:2',
        'distance_tiers' => 'array',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'effective_at' => 'datetime',
        'approved_at' => 'datetime',
        'priority' => 'integer',
        'version' => 'integer',
        'is_active' => 'boolean',
        'demand_adjustment_enabled' => 'boolean',
        'capacity_adjustment_enabled' => 'boolean',
    ];

    public function zone()
    {
        return $this->belongsTo(LogisticsZone::class, 'zone_id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function appliesTo(?int $zoneId, ?int $locationId): bool
    {
        if ($this->zone_id !== null && $this->zone_id !== $zoneId) {
            return false;
        }
        if ($this->location_id !== null && $this->location_id !== $locationId) {
            return false;
        }
        return true;
    }
}
