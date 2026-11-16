<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorCommission extends Model
{
    protected $fillable = [
        'vendor_id', 'order_id', 'order_detail_id', 'gross_amount',
        'commission_rate', 'commission_amount', 'platform_fee', 'net_amount',
        'currency', 'status',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:2',
        'commission_rate' => 'decimal:4',
        'commission_amount' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'net_amount' => 'decimal:2',
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
