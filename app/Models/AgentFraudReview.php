<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentFraudReview extends Model
{
    protected $fillable = [
        'agent_id', 'order_id', 'type', 'severity', 'score', 'signals',
        'status', 'decision', 'resolution_reason', 'evidence',
        'assigned_to', 'resolved_by', 'resolved_at',
    ];

    protected $casts = [
        'score' => 'integer',
        'signals' => 'array',
        'evidence' => 'array',
        'resolved_at' => 'datetime',
    ];

    public const TYPES = [
        'self_purchase', 'velocity', 'cancellation_rate', 'refund_abuse',
        'duplicate_account', 'collusion', 'location_mismatch', 'manual',
    ];

    public const SEVERITIES = ['low', 'medium', 'high', 'critical'];

    public const STATUSES = ['open', 'investigating', 'resolved', 'dismissed'];

    public function agent()
    {
        return $this->belongsTo(SalesRep::class, 'agent_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['open', 'investigating']);
    }
}
