<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecommendationEvent extends Model
{
    public const TYPES = ['view', 'impression', 'click', 'add_to_cart', 'purchase'];

    protected $fillable = [
        'event_uuid', 'type', 'algorithm_key', 'algorithm_version', 'surface',
        'subject_type', 'subject_id', 'product_id', 'category_id', 'vendor_id',
        'order_id', 'value_minor', 'currency', 'position', 'session_id',
        'is_valid', 'invalid_reason', 'meta', 'occurred_at',
    ];

    protected $casts = [
        'is_valid' => 'boolean',
        'value_minor' => 'integer',
        'algorithm_version' => 'integer',
        'position' => 'integer',
        'meta' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeValid($query)
    {
        return $query->where('is_valid', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
