<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentInvitation extends Model
{
    protected $fillable = [
        'inviter_sales_rep_id', 'name', 'email', 'phone', 'token', 'status',
        'accepted_user_id', 'accepted_sales_rep_id', 'expires_at', 'accepted_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public function inviter()
    {
        return $this->belongsTo(SalesRep::class, 'inviter_sales_rep_id');
    }

    public function acceptedUser()
    {
        return $this->belongsTo(User::class, 'accepted_user_id');
    }

    public function acceptedSalesRep()
    {
        return $this->belongsTo(SalesRep::class, 'accepted_sales_rep_id');
    }
}
