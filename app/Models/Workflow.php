<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Workflow extends Model
{
    protected $fillable = [
        'name', 'key', 'type', 'action', 'trigger', 'trigger_type', 'description',
        'config', 'definition', 'retry_policy', 'timeout_seconds', 'max_attempts',
        'version', 'is_active', 'last_run_at', 'next_run_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'config' => 'array',
        'definition' => 'array',
        'retry_policy' => 'array',
        'timeout_seconds' => 'integer',
        'max_attempts' => 'integer',
        'version' => 'integer',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];

    public function runs()
    {
        return $this->hasMany(AutomationRun::class)->orderByDesc('id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Registered action key for this workflow (falls back to `type`).
     */
    public function actionKey(): string
    {
        return $this->action ?: $this->type;
    }

    /**
     * Ordered list of step definitions. A workflow without an explicit
     * definition is treated as a single step invoking its action key.
     */
    public function steps(): array
    {
        $definition = $this->definition;

        if (is_array($definition) && ! empty($definition['steps'])) {
            return array_values($definition['steps']);
        }

        return [['action' => $this->actionKey(), 'input' => $this->config ?? []]];
    }
}
