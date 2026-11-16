<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentStatusHistory extends Model
{
    protected $fillable = [
        'agent_id', 'from_status', 'to_status', 'actor_id', 'reason', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function agent()
    {
        return $this->belongsTo(SalesRep::class, 'agent_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
