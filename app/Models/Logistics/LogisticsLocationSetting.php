<?php

namespace App\Models\Logistics;

use App\Models\Location;
use App\Models\Rider\Rider;
use Illuminate\Database\Eloquent\Model;

class LogisticsLocationSetting extends Model
{
    protected $table = 'logistics_location_settings';

    protected $primaryKey = 'location_id';

    public $incrementing = false;

    protected $fillable = [
        'location_id',
        'is_covered',
        'delivery_zone',
        'hub_name',
        'assigned_rider_id',
        'delivery_fee_override',
        'latitude',
        'longitude',
        'service_notes',
    ];

    protected $casts = [
        'is_covered' => 'boolean',
        'delivery_fee_override' => 'decimal:2',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function assignedRider()
    {
        return $this->belongsTo(Rider::class, 'assigned_rider_id');
    }
}
