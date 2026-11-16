<?php

namespace App\Models;

use App\Services\Rules\RuleEngine;
use Illuminate\Database\Eloquent\Model;

class BusinessRule extends Model
{
    protected $fillable = [
        'key', 'name', 'description', 'category', 'owner', 'value_type',
        'validation_rules', 'value', 'default_value', 'on_invalid', 'options',
        'is_active', 'is_managed', 'is_editable', 'version',
        'effective_from', 'effective_to', 'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_managed' => 'boolean',
        'is_editable' => 'boolean',
        'version' => 'integer',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
    ];

    public function revisions()
    {
        return $this->hasMany(BusinessRuleRevision::class)->orderBy('version');
    }

    public function scopeManaged($query)
    {
        return $query->where('is_managed', true);
    }

    /**
     * Governed read. Managed rules resolve through the engine (schema,
     * effective window, safe default, failure behaviour); unmanaged legacy
     * rules keep their historical behaviour.
     */
    public static function get($key, $default = null)
    {
        return app(RuleEngine::class)->string($key, $default);
    }

    public static function number($key, $default = 0)
    {
        return app(RuleEngine::class)->number($key, $default);
    }

    public static function truthy($key, $default = false)
    {
        return app(RuleEngine::class)->boolean($key, $default);
    }

    public static function selectValue($key, array $allowed, $default = null)
    {
        return app(RuleEngine::class)->select($key, $allowed, $default);
    }
}
