<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesAttribution extends Model
{
    protected $fillable = [
        'order_id', 'sales_rep_id', 'method', 'source', 'commission_rate',
        'policy_id', 'policy_version', 'commission_basis',
        'order_total', 'commission_amount', 'status',
        'agent_confirmed', 'confirmation_method', 'confirmed_at', 'confirmation_deadline',
        'dispute_status',
        'notes', 'created_by', 'approved_by', 'approved_at', 'attributed_at',
    ];

    protected $casts = [
        'commission_rate' => 'float',
        'order_total' => 'float',
        'commission_amount' => 'float',
        'policy_version' => 'integer',
        'agent_confirmed' => 'boolean',
        'approved_at' => 'datetime',
        'attributed_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'confirmation_deadline' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function rep()
    {
        return $this->belongsTo(SalesRep::class, 'sales_rep_id');
    }

    public function policy()
    {
        return $this->belongsTo(AgentCommissionPolicy::class, 'policy_id');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'approved', 'paid']);
    }
}
