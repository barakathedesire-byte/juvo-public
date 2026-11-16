<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentRankDefinition extends Model
{
    protected $fillable = [
        'name', 'slug', 'level', 'badge_color', 'description',
        'min_delivered_orders', 'min_sales_volume', 'min_quality_score', 'min_active_months',
        'bonus_rate', 'is_active',
    ];

    protected $casts = [
        'level' => 'integer',
        'min_delivered_orders' => 'integer',
        'min_sales_volume' => 'float',
        'min_quality_score' => 'float',
        'min_active_months' => 'integer',
        'bonus_rate' => 'float',
        'is_active' => 'boolean',
    ];

    public function histories()
    {
        return $this->hasMany(AgentRankHistory::class, 'rank_definition_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function qualifies(float $salesVolume, int $deliveredOrders, ?float $qualityScore = null): bool
    {
        if ($salesVolume < (float) $this->min_sales_volume) {
            return false;
        }

        if ($deliveredOrders < (int) $this->min_delivered_orders) {
            return false;
        }

        if ($this->min_quality_score !== null && $qualityScore !== null && $qualityScore < (float) $this->min_quality_score) {
            return false;
        }

        return true;
    }
}
