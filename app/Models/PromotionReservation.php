<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An atomic, idempotent hold against a promotion's budget. A reservation is
 * created (held) when an order is priced, then committed or released. The
 * unique `reference` makes reserving safe to retry.
 */
class PromotionReservation extends Model
{
    protected $fillable = ['promotion_id', 'order_id', 'reference', 'amount', 'status', 'expires_at'];

    protected $casts = [
        'amount' => 'decimal:2',
        'expires_at' => 'datetime',
    ];

    public function promotion()
    {
        return $this->belongsTo(Promotion::class);
    }
}
