<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'order_id',
        'customer_id',
        'vendor_id',
        'amount',
        'currency',
        'status',
        'refund_id',
        'reason',
        'admin_note',
        'response',
    ];

    protected $casts = [
        'response' => 'array',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['requested', 'approved'], true);
    }
}
