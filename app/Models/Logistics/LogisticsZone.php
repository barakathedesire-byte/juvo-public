<?php

namespace App\Models\Logistics;

use App\Models\Location;
use App\Models\Rider\Rider;
use Illuminate\Database\Eloquent\Model;

class LogisticsZone extends Model
{
    protected $fillable = [
        'name', 'code', 'description', 'polygon', 'primary_hub_id',
        'is_active', 'capacity', 'service_restrictions',
    ];

    protected $casts = [
        'polygon' => 'array',
        'is_active' => 'boolean',
        'capacity' => 'integer',
    ];

    public function locations()
    {
        return $this->belongsToMany(Location::class, 'logistics_zone_locations', 'zone_id', 'location_id')->withTimestamps();
    }

    public function riders()
    {
        return $this->belongsToMany(Rider::class, 'logistics_zone_riders', 'zone_id', 'rider_id')->withTimestamps();
    }

    public function primaryHub()
    {
        return $this->belongsTo(LogisticsHub::class, 'primary_hub_id');
    }

    public function hubs()
    {
        return $this->hasMany(LogisticsHub::class, 'zone_id');
    }

    public function pricingRules()
    {
        return $this->hasMany(LogisticsDeliveryPricingRule::class, 'zone_id');
    }
}
