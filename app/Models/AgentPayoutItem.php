<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentPayoutItem extends Model
{
    protected $fillable = [
        'agent_payout_request_id', 'agent_commission_entry_id', 'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function payoutRequest()
    {
        return $this->belongsTo(AgentPayoutRequest::class, 'agent_payout_request_id');
    }

    public function commissionEntry()
    {
        return $this->belongsTo(AgentCommissionEntry::class, 'agent_commission_entry_id');
    }
}
