<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerAddress extends Model
{
    protected $fillable = [
        'customer_id', 'label', 'name', 'phone', 'address', 'suite',
        'city', 'state', 'postal_code', 'country', 'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function scopeDefault($query)
    {
        return $query->orderByDesc('is_default')->orderBy('id');
    }
}
