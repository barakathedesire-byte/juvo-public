<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentCommissionEntry extends Model
{
    protected $fillable = [
        'agent_id', 'order_id', 'order_detail_id',
        'policy_id', 'policy_version',
        'role', 'basis', 'basis_amount', 'rate', 'amount', 'currency',
        'state', 'hold_reason',
        'reversal_of_id', 'payout_request_id',
        'eligible_at', 'approved_at', 'paid_at', 'reversed_at',
        'dedup_key', 'notes',
    ];

    protected $casts = [
        'policy_version' => 'integer',
        'basis_amount' => 'decimal:2',
        'rate' => 'decimal:4',
        'amount' => 'decimal:2',
        'eligible_at' => 'datetime',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    public const ROLES = ['direct', 'team', 'bonus', 'adjustment'];

    public const STATES = [
        'pending', 'provisional', 'eligible', 'approved', 'payable',
        'paid', 'reversed', 'held', 'under_review',
    ];

    public const PAYABLE_STATES = ['eligible', 'approved', 'payable'];

    public function agent()
    {
        return $this->belongsTo(SalesRep::class, 'agent_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function orderDetail()
    {
        return $this->belongsTo(OrderDetail::class, 'order_detail_id');
    }

    public function policy()
    {
        return $this->belongsTo(AgentCommissionPolicy::class, 'policy_id');
    }

    public function payoutRequest()
    {
        return $this->belongsTo(AgentPayoutRequest::class, 'payout_request_id');
    }

    public function reversalOf()
    {
        return $this->belongsTo(AgentCommissionEntry::class, 'reversal_of_id');
    }

    public function scopeForAgent($query, $agentId)
    {
        return $query->where('agent_id', $agentId);
    }

    public function scopePayable($query)
    {
        return $query->whereIn('state', self::PAYABLE_STATES);
    }
}
