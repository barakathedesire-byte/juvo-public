<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentSavedProduct extends Model
{
    protected $fillable = ['sales_rep_id', 'product_id'];

    public function salesRep()
    {
        return $this->belongsTo(SalesRep::class, 'sales_rep_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
