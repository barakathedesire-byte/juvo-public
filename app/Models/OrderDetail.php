<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderDetail extends Model
{
    use HasFactory;

    protected $table = 'order_details';

    protected $fillable = [
        'order_id',
        'product_id',
        'variant_id',
        'quantity',
        'price',
        'discount_amount',
        'funding_breakdown',
    ];

    protected $casts = [
        'discount_amount' => 'decimal:2',
        'funding_breakdown' => 'array',
    ];

    /** 🔹 Belongs to order */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /** 🔹 Belongs to product */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /** 🔹 Belongs to the exact variant sold */
    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
