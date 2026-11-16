<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorTransaction extends Model
{
    protected $fillable = [
        'vendor_id', 'type', 'amount', 'currency', 'balance_after',
        'order_id', 'reference', 'description', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
