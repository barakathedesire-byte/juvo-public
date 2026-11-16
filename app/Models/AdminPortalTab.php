<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminPortalTab extends Model
{
    protected $fillable = [
        'label', 'icon', 'url', 'group', 'permission', 'sort', 'is_enabled', 'is_custom',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'is_custom' => 'boolean',
        'sort' => 'integer',
    ];

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }
}
