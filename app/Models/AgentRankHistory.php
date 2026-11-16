<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentRankHistory extends Model
{
    protected $fillable = [
        'agent_id', 'rank_definition_id', 'previous_rank_id', 'period',
        'sales_volume', 'delivered_orders', 'quality_score',
        'promoted', 'reason', 'calculated_at',
    ];

    protected $casts = [
        'sales_volume' => 'decimal:2',
        'delivered_orders' => 'integer',
        'quality_score' => 'decimal:2',
        'promoted' => 'boolean',
        'calculated_at' => 'datetime',
    ];

    public function agent()
    {
        return $this->belongsTo(SalesRep::class, 'agent_id');
    }

    public function rank()
    {
        return $this->belongsTo(AgentRankDefinition::class, 'rank_definition_id');
    }

    public function previousRank()
    {
        return $this->belongsTo(AgentRankDefinition::class, 'previous_rank_id');
    }
}
