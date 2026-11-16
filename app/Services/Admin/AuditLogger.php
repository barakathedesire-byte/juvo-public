<?php

namespace App\Services\Admin;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Append-only audit trail. Ordinary staff must not be able to delete or edit
 * records, so this service only ever inserts.
 */
class AuditLogger
{
    public function log(
        string $action,
        ?Model $auditable = null,
        array $before = [],
        array $after = [],
        ?string $reason = null,
        ?Model $actor = null,
    ): AuditLog {
        $actor = $actor ?? Auth::user();

        return AuditLog::create([
            'actor_type' => $actor ? $actor->getMorphClass() : null,
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'before' => $before ?: null,
            'after' => $after ?: null,
            'reason' => $reason,
            'ip' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
        ]);
    }

    public static function record(
        string $action,
        ?Model $auditable = null,
        array $before = [],
        array $after = [],
        ?string $reason = null,
        ?Model $actor = null,
    ): AuditLog {
        return app(static::class)->log($action, $auditable, $before, $after, $reason, $actor);
    }
}
