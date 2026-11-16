<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerFollowUp extends Model
{
    protected $fillable = [
        'customer_id', 'sales_rep_id', 'reason', 'notes', 'scheduled_at', 'completed_at', 'status',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesRep()
    {
        return $this->belongsTo(SalesRep::class, 'sales_rep_id');
    }

    public function isOverdue(): bool
    {
        return $this->completed_at === null && $this->scheduled_at && $this->scheduled_at->isPast();
    }
}
