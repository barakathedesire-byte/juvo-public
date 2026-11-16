<?php

namespace App\Models\Logistics;

use Illuminate\Database\Eloquent\Model;

class RiskCase extends Model
{
    protected $table = 'logistics_risk_cases';

    protected $fillable = [
        'case_number', 'anomaly_type', 'severity', 'subject_type', 'subject_id',
        'title', 'description', 'risk_score', 'factors', 'status', 'assigned_to',
        'resolution', 'resolution_notes', 'acknowledged_at', 'resolved_at', 'closed_at',
    ];

    protected $casts = [
        'factors' => 'array',
        'risk_score' => 'decimal:2',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function assignedTo() { return $this->belongsTo(LogisticsAdmin::class, 'assigned_to'); }
    public function evidence() { return $this->hasMany(RiskEvidence::class, 'case_id'); }
}
