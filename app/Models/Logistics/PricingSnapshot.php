<?php

namespace App\Models\Logistics;

use App\Models\Order;
use Illuminate\Database\Eloquent\Model;

class PricingSnapshot extends Model
{
    protected $table = 'logistics_pricing_snapshots';

    protected $fillable = ['order_id', 'rule_ids', 'breakdown', 'final_fee', 'version'];
    protected $casts = ['rule_ids' => 'array', 'breakdown' => 'array', 'final_fee' => 'decimal:2', 'version' => 'integer'];
    public function order() { return $this->belongsTo(Order::class); }
}
