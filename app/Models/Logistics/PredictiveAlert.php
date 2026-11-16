<?php

namespace App\Models\Logistics;

use Illuminate\Database\Eloquent\Model;

class PredictiveAlert extends Model
{
    protected $table = 'logistics_predictive_alerts';

    protected $fillable = [
        'alert_type', 'severity', 'subject_type', 'subject_id', 'title', 'description',
        'factors', 'recommended_action', 'status', 'assigned_to', 'acknowledged_at', 'resolved_at',
    ];

    protected $casts = [
        'factors' => 'array',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function assignedTo() { return $this->belongsTo(LogisticsAdmin::class, 'assigned_to'); }
}
