<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentPromotionShare extends Model
{
    protected $fillable = ['sales_rep_id', 'promotion_id', 'product_id', 'channel'];

    public function salesRep()
    {
        return $this->belongsTo(SalesRep::class, 'sales_rep_id');
    }

    public function promotion()
    {
        return $this->belongsTo(Promotion::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
