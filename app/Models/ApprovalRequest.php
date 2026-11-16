<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalRequest extends Model
{
    protected $fillable = [
        'reference', 'policy_key', 'ability', 'module', 'approvable_type', 'approvable_id',
        'requester_id', 'requester_role', 'reason', 'payload', 'amount', 'risk',
        'required_approvals', 'received_approvals', 'status', 'assigned_to',
        'decided_by', 'decided_at', 'decision_reason', 'executed_at', 'meta',
    ];

    protected $casts = [
        'payload' => 'array',
        'meta' => 'array',
        'amount' => 'decimal:2',
        'required_approvals' => 'integer',
        'received_approvals' => 'integer',
        'decided_at' => 'datetime',
        'executed_at' => 'datetime',
    ];

    public const OPEN_STATUSES = ['pending', 'escalated'];

    public function approvable()
    {
        return $this->morphTo();
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function decider()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function steps()
    {
        return $this->hasMany(ApprovalRequestStep::class)->orderBy('id');
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }
}
