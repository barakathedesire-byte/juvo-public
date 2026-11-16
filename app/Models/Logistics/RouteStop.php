<?php

namespace App\Models\Logistics;

use App\Models\Order;
use App\Models\Location;
use Illuminate\Database\Eloquent\Model;

class RouteStop extends Model
{
    protected $table = 'logistics_route_stops';

    protected $fillable = [
        'route_plan_id', 'order_id', 'sequence', 'stop_type', 'location_id',
        'latitude', 'longitude', 'status', 'planned_arrival_at', 'actual_arrival_at',
        'distance_from_previous_km', 'duration_from_previous_minutes', 'notes',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'distance_from_previous_km' => 'decimal:2',
        'duration_from_previous_minutes' => 'integer',
        'planned_arrival_at' => 'datetime',
        'actual_arrival_at' => 'datetime',
    ];

    public function plan() { return $this->belongsTo(RoutePlan::class, 'route_plan_id'); }
    public function order() { return $this->belongsTo(Order::class); }
    public function location() { return $this->belongsTo(Location::class); }
}
