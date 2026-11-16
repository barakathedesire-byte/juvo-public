<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An immutable record of a promotion actually applied to an order line. Keeps
 * the funding allocation so refunds/reversals can follow the original split.
 */
class PromotionRedemption extends Model
{
    protected $fillable = [
        'promotion_id', 'order_id', 'order_detail_id', 'discount_amount',
        'funding_source', 'funded_vendor_id', 'reference',
    ];

    protected $casts = [
        'discount_amount' => 'decimal:2',
    ];

    public function promotion()
    {
        return $this->belongsTo(Promotion::class);
    }
}
