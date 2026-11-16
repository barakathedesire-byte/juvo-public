<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentPayoutRequest extends Model
{
    protected $fillable = [
        'reference', 'agent_id', 'amount', 'currency',
        'method', 'account_name', 'account_number',
        'status', 'provider_reference',
        'requested_at', 'approved_at', 'processing_at', 'paid_at', 'rejected_at',
        'approved_by', 'processed_by', 'notes', 'rejection_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'account_number' => 'encrypted',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'processing_at' => 'datetime',
        'paid_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    protected $hidden = [
        'account_number',
    ];

    public const STATUSES = [
        'requested', 'approved', 'processing', 'paid', 'rejected', 'failed',
    ];

    public const OPEN_STATUSES = ['requested', 'approved', 'processing'];

    public function agent()
    {
        return $this->belongsTo(SalesRep::class, 'agent_id');
    }

    public function items()
    {
        return $this->hasMany(AgentPayoutItem::class, 'agent_payout_request_id');
    }

    public function entries()
    {
        return $this->hasMany(AgentCommissionEntry::class, 'payout_request_id');
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
