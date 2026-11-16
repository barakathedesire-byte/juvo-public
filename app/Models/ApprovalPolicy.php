<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalPolicy extends Model
{
    protected $fillable = [
        'key', 'name', 'description', 'module', 'requires_approval',
        'min_amount', 'max_amount', 'approver_permission', 'stages',
        'risk', 'is_active', 'meta',
    ];

    protected $casts = [
        'requires_approval' => 'boolean',
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'stages' => 'integer',
        'is_active' => 'boolean',
        'meta' => 'array',
    ];

    public static function forAbility(string $ability): ?self
    {
        return static::where('key', $ability)->where('is_active', true)->first();
    }
}
