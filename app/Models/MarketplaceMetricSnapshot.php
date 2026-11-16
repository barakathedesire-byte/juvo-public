<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceMetricSnapshot extends Model
{
    protected $fillable = [
        'metric', 'scope', 'scope_id', 'period_date', 'value', 'sample_size', 'meta',
    ];

    protected $casts = [
        'value' => 'float',
        'sample_size' => 'integer',
        'period_date' => 'date',
        'meta' => 'array',
    ];

    public function scopeMetric($query, string $metric)
    {
        return $query->where('metric', $metric);
    }
}
