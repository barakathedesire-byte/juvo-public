<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadFollowUp extends Model
{
    protected $fillable = ['sales_lead_id', 'sales_rep_id', 'reason', 'notes', 'scheduled_at', 'completed_at', 'status'];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function lead()
    {
        return $this->belongsTo(SalesLead::class, 'sales_lead_id');
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
