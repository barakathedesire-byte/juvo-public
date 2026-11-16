<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorLedgerEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'vendor_id',
        'order_id',
        'gross_amount',
        'marketplace_commission',
        'net_vendor_earning',
        'status',
        'commission_breakdown',
        'cleared_at',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:2',
        'marketplace_commission' => 'decimal:2',
        'net_vendor_earning' => 'decimal:2',
        'commission_breakdown' => 'array',
        'cleared_at' => 'datetime',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function scopePendingSettlement($query)
    {
        return $query->where('status', 'pending_settlement');
    }

    public function scopeCleared($query)
    {
        return $query->where('status', 'cleared');
    }

    public function markCleared(): void
    {
        $this->update([
            'status' => 'cleared',
            'cleared_at' => now(),
        ]);
    }
}
