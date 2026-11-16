<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id', 'product_id', 'rating', 'review', 'vendor_reply', 'vendor_replied_at', 'is_approved', 'is_verified_purchase',
    ];

    protected $casts = [
        'is_approved' => 'boolean',
        'is_verified_purchase' => 'boolean',
        'vendor_replied_at' => 'datetime',
    ];

    /**
     * Get the customer that owns the review.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the product that the review belongs to.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeApproved($query)
    {
        return $query->where('is_approved', 1);
    }
}
