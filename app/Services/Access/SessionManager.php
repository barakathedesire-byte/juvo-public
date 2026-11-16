<?php

namespace App\Services\Access;

use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Centralized session lifecycle control. Revoking is version-based so it works
 * with any session driver (file/cookie/database): the account's `auth_version`
 * is bumped and every outstanding session is forced to re-authenticate on its
 * next request by EnforceCurrentSessionVersion.
 */
class SessionManager
{
    /**
     * End every active session for the account. Call this on deactivation,
     * credential reset, or a security-sensitive permission change.
     */
    public function revoke(User $user): void
    {
        $user->forceFill([
            'remember_token' => null,
            'auth_version' => (int) $user->auth_version + 1,
        ])->save();

        if (config('session.driver') === 'database' && Schema::hasTable('sessions')) {
            \Illuminate\Support\Facades\DB::table('sessions')->where('user_id', $user->id)->delete();
        }
    }

    /**
     * Revoke sessions for every user holding the given role (used when a role's
     * permissions change).
     *
     * @param  \Spatie\Permission\Models\Role  $role
     * @return int number of accounts affected
     */
    public function revokeForRole($role): int
    {
        $count = 0;

        foreach ($role->users()->get() as $user) {
            $this->revoke($user);
            $count++;
        }

        return $count;
    }
}
