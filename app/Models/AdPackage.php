<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdPackage extends Model
{
    protected $fillable = [
        'name', 'description', 'placement_id', 'duration_days',
        'price', 'impressions_included', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'duration_days' => 'integer',
        'price' => 'float',
        'impressions_included' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function placement()
    {
        return $this->belongsTo(AdPlacement::class, 'placement_id');
    }
}
