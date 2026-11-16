<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadStatusHistory extends Model
{
    protected $fillable = ['sales_lead_id', 'sales_rep_id', 'from_status', 'to_status', 'reason'];

    public function lead()
    {
        return $this->belongsTo(SalesLead::class, 'sales_lead_id');
    }

    public function salesRep()
    {
        return $this->belongsTo(SalesRep::class, 'sales_rep_id');
    }
}
