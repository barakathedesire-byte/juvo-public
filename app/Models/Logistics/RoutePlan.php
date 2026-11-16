<?php

namespace App\Models\Logistics;

use App\Models\Rider\Rider;
use Illuminate\Database\Eloquent\Model;

class RoutePlan extends Model
{
    protected $table = 'logistics_route_plans';

    protected $fillable = [
        'rider_id', 'status', 'optimization_method', 'planned_distance_km',
        'planned_duration_minutes', 'actual_distance_km', 'actual_duration_minutes',
        'provider', 'provider_response', 'started_at', 'completed_at', 'notes',
    ];

    protected $casts = [
        'planned_distance_km' => 'decimal:2',
        'actual_distance_km' => 'decimal:2',
        'planned_duration_minutes' => 'integer',
        'actual_duration_minutes' => 'integer',
        'provider_response' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function rider() { return $this->belongsTo(Rider::class); }
    public function stops() { return $this->hasMany(RouteStop::class, 'route_plan_id')->orderBy('sequence'); }
}
