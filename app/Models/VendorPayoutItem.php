<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorPayoutItem extends Model
{
    protected $fillable = [
        'vendor_payout_id', 'vendor_transaction_id', 'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function payout()
    {
        return $this->belongsTo(VendorPayout::class, 'vendor_payout_id');
    }

    public function transaction()
    {
        return $this->belongsTo(VendorTransaction::class, 'vendor_transaction_id');
    }
}
