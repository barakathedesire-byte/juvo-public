<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentCommissionPolicy extends Model
{
    protected $fillable = [
        'policy_key', 'name', 'description',
        'rate', 'fixed_amount', 'basis',
        'min_order_value', 'max_commission',
        'eligible_category_ids', 'excluded_category_ids',
        'include_tax', 'include_delivery_fee',
        'allow_self_purchase', 'allow_guest_orders',
        'team_enabled', 'team_rate', 'team_max_depth', 'team_max_total_rate',
        'version', 'priority', 'effective_from', 'effective_to',
        'is_active', 'is_default', 'created_by', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'rate' => 'float',
        'fixed_amount' => 'float',
        'min_order_value' => 'float',
        'max_commission' => 'float',
        'team_rate' => 'float',
        'team_max_total_rate' => 'float',
        'team_max_depth' => 'integer',
        'eligible_category_ids' => 'array',
        'excluded_category_ids' => 'array',
        'include_tax' => 'boolean',
        'include_delivery_fee' => 'boolean',
        'allow_self_purchase' => 'boolean',
        'allow_guest_orders' => 'boolean',
        'team_enabled' => 'boolean',
        'version' => 'integer',
        'priority' => 'integer',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public const BASES = [
        'order_total',
        'subtotal',
        'merch_after_discount',
        'merch_after_discount_ex_tax',
    ];

    public function entries()
    {
        return $this->hasMany(AgentCommissionEntry::class, 'policy_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isCurrentlyEffective(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = now();

        if ($this->effective_from && $this->effective_from->greaterThan($now)) {
            return false;
        }

        if ($this->effective_to && $this->effective_to->lessThan($now)) {
            return false;
        }

        return true;
    }
}
