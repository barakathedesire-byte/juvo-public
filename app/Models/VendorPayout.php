<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorPayout extends Model
{
    protected $fillable = [
        'vendor_id', 'reference', 'amount', 'currency', 'method', 'payout_method_id',
        'status', 'requested_at', 'approved_at', 'processed_at', 'paid_at',
        'approved_by', 'processed_by', 'notes', 'rejection_reason', 'provider_reference',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'processed_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function payoutMethod()
    {
        return $this->belongsTo(VendorPayoutMethod::class, 'payout_method_id');
    }

    public function items()
    {
        return $this->hasMany(VendorPayoutItem::class);
    }
}
