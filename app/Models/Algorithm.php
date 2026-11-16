<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Algorithm extends Model
{
    protected $fillable = [
        'key', 'name', 'category', 'description', 'status', 'is_enabled',
        'version', 'is_implemented', 'config', 'config_schema', 'meta',
        'owner', 'created_by',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'is_implemented' => 'boolean',
        'version' => 'integer',
        'config' => 'array',
        'config_schema' => 'array',
        'meta' => 'array',
    ];

    public function versions()
    {
        return $this->hasMany(AlgorithmVersion::class)->orderByDesc('version');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')->where('is_enabled', true);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->is_enabled;
    }

    /**
     * Merge stored config over the registered defaults.
     */
    public function effectiveConfig(): array
    {
        $defaults = is_array($this->config_schema) ? ($this->config_schema['defaults'] ?? []) : [];

        return array_merge($defaults, is_array($this->config) ? $this->config : []);
    }
}
