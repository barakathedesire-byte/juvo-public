<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchQuery extends Model
{
    protected $fillable = [
        'query', 'normalized', 'locale', 'surface', 'result_count',
        'is_zero_result', 'latency_ms', 'algorithm_key', 'algorithm_version',
        'filters', 'sort', 'session_id', 'ip_hash', 'occurred_at',
    ];

    protected $casts = [
        'is_zero_result' => 'boolean',
        'result_count' => 'integer',
        'latency_ms' => 'integer',
        'filters' => 'array',
        'occurred_at' => 'datetime',
    ];
}
