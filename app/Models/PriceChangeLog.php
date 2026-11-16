<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only price history. Every change to a stored price/discount carries
 * the actor, the previous and new value, the source and (ideally) a reason so
 * the reference/original price is never invented.
 */
class PriceChangeLog extends Model
{
    protected $fillable = [
        'product_id', 'variant_id', 'vendor_id', 'field', 'old_value', 'new_value',
        'source', 'actor_type', 'actor_id', 'reason', 'meta',
    ];

    protected $casts = [
        'old_value' => 'decimal:2',
        'new_value' => 'decimal:2',
        'meta' => 'array',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
