<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Derived, rebuildable search index row (one per product + locale). Never an
 * authority for eligibility or price — always re-derivable from the catalog.
 */
class ProductSearchDocument extends Model
{
    protected $fillable = [
        'product_id', 'locale', 'vendor_id', 'category_id', 'brand_id',
        'title', 'keywords', 'price_min', 'discount_price_min', 'in_stock',
        'is_promoted', 'rating', 'review_count', 'popularity', 'meta', 'refreshed_at',
    ];

    protected $casts = [
        'price_min' => 'float',
        'discount_price_min' => 'float',
        'in_stock' => 'boolean',
        'is_promoted' => 'boolean',
        'rating' => 'float',
        'review_count' => 'integer',
        'popularity' => 'integer',
        'meta' => 'array',
        'refreshed_at' => 'datetime',
    ];
}
