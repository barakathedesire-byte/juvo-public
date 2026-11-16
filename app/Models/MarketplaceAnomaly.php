<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceAnomaly extends Model
{
    public const STATUSES = ['open', 'acknowledged', 'assigned', 'resolved', 'false_positive'];

    protected $fillable = [
        'rule_id', 'dedup_key', 'metric', 'scope', 'scope_id',
        'observed_value', 'expected_value', 'deviation', 'severity', 'status',
        'assigned_to', 'meta', 'detected_at', 'acknowledged_at',
        'resolved_at', 'resolution_note',
    ];

    protected $casts = [
        'observed_value' => 'float',
        'expected_value' => 'float',
        'deviation' => 'float',
        'meta' => 'array',
        'detected_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function rule()
    {
        return $this->belongsTo(MarketplaceAnomalyRule::class, 'rule_id');
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['open', 'acknowledged', 'assigned']);
    }
}
