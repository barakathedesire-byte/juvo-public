<?php

namespace App\Models\Logistics;

use Illuminate\Database\Eloquent\Model;

class DispatchConfig extends Model
{
    protected $table = 'logistics_dispatch_configs';

    protected $fillable = [
        'auto_dispatch_enabled', 'max_active_assignments', 'assignment_timeout_minutes',
        'minimum_score', 'batch_size', 'scoring_weights', 'eligible_zones',
        'eligible_order_types', 'vehicle_compatibility', 'require_manual_approval',
    ];

    protected $casts = [
        'auto_dispatch_enabled' => 'boolean',
        'max_active_assignments' => 'integer',
        'assignment_timeout_minutes' => 'integer',
        'minimum_score' => 'decimal:2',
        'batch_size' => 'integer',
        'scoring_weights' => 'array',
        'eligible_zones' => 'array',
        'eligible_order_types' => 'array',
        'vehicle_compatibility' => 'array',
        'require_manual_approval' => 'boolean',
    ];

    public static function current(): self
    {
        return self::firstOrCreate([], [
            'scoring_weights' => [
                'availability' => 25,
                'workload' => 20,
                'distance' => 20,
                'zone' => 15,
                'performance' => 10,
                'vehicle' => 5,
                'priority' => 5,
            ],
        ]);
    }
}
