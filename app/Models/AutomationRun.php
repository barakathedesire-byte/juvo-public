<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutomationRun extends Model
{
    public const STATUSES = ['queued', 'running', 'succeeded', 'failed', 'retrying', 'dead_letter', 'cancelled'];

    protected $fillable = [
        'workflow_id', 'run_uuid', 'trigger', 'status', 'attempt', 'max_attempts',
        'input', 'output', 'error', 'started_at', 'finished_at', 'next_retry_at',
        'parent_run_id', 'triggered_by',
    ];

    protected $casts = [
        'input' => 'array',
        'output' => 'array',
        'attempt' => 'integer',
        'max_attempts' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'next_retry_at' => 'datetime',
    ];

    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }

    public function logs()
    {
        return $this->hasMany(AutomationRunLog::class, 'run_id')->orderBy('id');
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['succeeded', 'failed', 'dead_letter', 'cancelled'], true);
    }

    public function scopeDue($query)
    {
        return $query->whereIn('status', ['queued', 'retrying'])
            ->where(function ($q) {
                $q->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', now());
            });
    }
}
