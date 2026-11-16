<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportSlaPolicy extends Model
{
    protected $fillable = [
        'priority', 'first_response_minutes', 'resolution_minutes', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'first_response_minutes' => 'integer',
        'resolution_minutes' => 'integer',
    ];
}
