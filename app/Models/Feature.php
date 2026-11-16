<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feature extends Model
{
    protected $fillable = [
        'key', 'name', 'description', 'module', 'is_enabled',
        'rollout_percentage', 'meta', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'rollout_percentage' => 'integer',
        'meta' => 'array',
    ];

    /**
     * Deterministic percentage rollout: the same subject always gets the same
     * answer for a given feature until the rollout percentage changes.
     */
    public function enabledFor(int|string|null $subjectId = null): bool
    {
        if (! $this->is_enabled) {
            return false;
        }

        $rollout = max(0, min(100, (int) $this->rollout_percentage));

        if ($rollout >= 100) {
            return true;
        }

        if ($subjectId === null) {
            return false;
        }

        $bucket = crc32($this->key.':'.$subjectId) % 100;

        return $bucket < $rollout;
    }
}
