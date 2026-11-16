<?php

namespace App\Models\Logistics;

use App\Models\Location;
use Illuminate\Database\Eloquent\Model;

class LogisticsHub extends Model
{
    protected $fillable = [
        'name', 'code', 'location_id', 'zone_id', 'address',
        'latitude', 'longitude', 'operating_hours', 'capacity', 'is_active',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'capacity' => 'integer',
        'is_active' => 'boolean',
    ];

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function zone()
    {
        return $this->belongsTo(LogisticsZone::class, 'zone_id');
    }
}
