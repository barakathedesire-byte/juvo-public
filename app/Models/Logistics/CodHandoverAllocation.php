<?php

namespace App\Models\Logistics;

use App\Models\Order;
use Illuminate\Database\Eloquent\Model;

class CodHandoverAllocation extends Model
{
    protected $fillable = ['cod_handover_id', 'order_id', 'amount'];

    protected $casts = ['amount' => 'decimal:2'];

    public function handover()
    {
        return $this->belongsTo(CodHandover::class, 'cod_handover_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
