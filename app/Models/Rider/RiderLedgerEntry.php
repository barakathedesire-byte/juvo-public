<?php

namespace App\Models\Rider;

use App\Models\Order;
use Illuminate\Database\Eloquent\Model;

class RiderLedgerEntry extends Model
{
    protected $fillable = [
        'reference', 'rider_id', 'order_id', 'rider_payout_id', 'type', 'direction',
        'amount', 'currency', 'status', 'description', 'source_key', 'meta',
        'created_by_type', 'created_by_id', 'created_by_name',
        'approved_by_type', 'approved_by_id', 'approved_by_name', 'approved_at', 'occurred_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'meta' => 'array',
        'approved_at' => 'datetime',
        'occurred_at' => 'datetime',
    ];

    public const TYPES = [
        'delivery_earning' => 'Delivery earning',
        'bonus' => 'Bonus',
        'adjustment' => 'Adjustment',
        'deduction' => 'Deduction',
        'payout' => 'Payout',
    ];

    public const ACTIVE_STATUSES = ['available', 'pending', 'paid'];

    public function rider()
    {
        return $this->belongsTo(Rider::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function payout()
    {
        return $this->belongsTo(RiderPayout::class, 'rider_payout_id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst(str_replace('_', ' ', $this->type));
    }
}
