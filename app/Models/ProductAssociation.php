<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductAssociation extends Model
{
    public const TYPES = ['similar', 'complementary'];

    protected $fillable = [
        'product_id', 'related_product_id', 'type', 'score',
        'support_count', 'meta', 'computed_at',
    ];

    protected $casts = [
        'score' => 'float',
        'support_count' => 'integer',
        'meta' => 'array',
        'computed_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function related()
    {
        return $this->belongsTo(Product::class, 'related_product_id');
    }
}
