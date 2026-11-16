<?php

namespace Tests\Concerns;

use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

trait GrantsAdminAccess
{
    /**
     * Create an admin user with the platform's full-access role so the
     * RBAC middleware lets the request through.
     */
    protected function admin(array $attrs = []): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $user = User::factory()->create(array_merge([
            'role' => 'admin',
            'email' => 'admin-'.Str::lower(Str::ulid()).'@example.com',
        ], $attrs));
        $user->assignRole($role);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }
}
