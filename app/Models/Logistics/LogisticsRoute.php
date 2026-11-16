<?php

namespace App\Models\Logistics;

use App\Models\Location;
use Illuminate\Database\Eloquent\Model;

class LogisticsRoute extends Model
{
    protected $table = 'logistics_routes';

    protected $fillable = [
        'origin_location_id', 'destination_location_id', 'distance_km',
        'duration_minutes', 'route_geometry', 'status', 'notes',
    ];

    protected $casts = [
        'distance_km' => 'decimal:2',
        'duration_minutes' => 'integer',
        'route_geometry' => 'array',
    ];

    public function origin()
    {
        return $this->belongsTo(Location::class, 'origin_location_id');
    }

    public function destination()
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }
}
