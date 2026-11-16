<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class RoleDecisionPolicy extends Model
{
    public const MODE_ALLOWED = 'allowed';

    public const MODE_LIMITED = 'limited';

    public const MODE_REQUIRES_APPROVAL = 'requires_approval';

    public const MODE_PROHIBITED = 'prohibited';

    protected $fillable = ['role_id', 'ability', 'mode', 'limits'];

    protected $casts = [
        'limits' => 'array',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * Numeric threshold for a governed ability, or null when unlimited.
     */
    public static function thresholdFor(?RoleDecisionPolicy $policy, string $key): ?float
    {
        if (! $policy || ! is_array($policy->limits)) {
            return null;
        }

        $value = $policy->limits[$key] ?? null;

        return $value === null ? null : (float) $value;
    }
}
