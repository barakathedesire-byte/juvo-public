<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceAnomalyRule extends Model
{
    public const METHODS = ['threshold', 'zscore', 'iqr', 'percent_change'];

    protected $fillable = [
        'key', 'name', 'metric', 'method', 'config', 'severity',
        'scope', 'window', 'min_sample', 'is_enabled', 'meta',
    ];

    protected $casts = [
        'config' => 'array',
        'meta' => 'array',
        'window' => 'integer',
        'min_sample' => 'integer',
        'is_enabled' => 'boolean',
    ];

    public function anomalies()
    {
        return $this->hasMany(MarketplaceAnomaly::class, 'rule_id');
    }

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }
}
