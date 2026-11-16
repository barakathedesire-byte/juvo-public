<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdPlacement extends Model
{
    protected $fillable = [
        'key', 'name', 'description', 'width', 'height',
        'base_price', 'pricing_model', 'format', 'min_bid_minor', 'settings',
        'max_active', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'base_price' => 'float',
        'min_bid_minor' => 'integer',
        'settings' => 'array',
        'max_active' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function campaigns()
    {
        return $this->hasMany(AdCampaign::class, 'placement_id');
    }

    public function packages()
    {
        return $this->hasMany(AdPackage::class, 'placement_id');
    }

    public function activeCampaignCount(): int
    {
        return $this->campaigns()->where('status', 'active')->count();
    }

    public function isFull(): bool
    {
        return $this->max_active > 0 && $this->activeCampaignCount() >= $this->max_active;
    }
}
