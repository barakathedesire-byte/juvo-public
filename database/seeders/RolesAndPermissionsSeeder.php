<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Capability groups mapped to the admin panel sections they protect.
     */
    public const PERMISSIONS = [
        'manage-settings',
        'manage-vendors',
        'manage-products',
        'manage-orders',
        'manage-finances',
        'manage-ads',
        'manage-homepage',
        'manage-customers',
        'manage-reviews',
        'manage-content',
        'manage-users',
        'manage-support',
        'view-analytics',
    ];

    /**
     * role => permissions granted.
     */
    public const ROLES = [
        'super_admin' => '*',
        'admin' => '*',
        'manager' => [
            'manage-vendors',
            'manage-products',
            'manage-orders',
            'manage-customers',
            'manage-reviews',
            'manage-content',
            'manage-homepage',
            'manage-support',
            'view-analytics',
        ],
        'finance' => [
            'manage-finances',
            'manage-orders',
            'view-analytics',
        ],
        'support' => [
            'manage-orders',
            'manage-customers',
            'manage-reviews',
            'manage-support',
            'view-analytics',
        ],
        'ad_manager' => [
            'manage-ads',
            'view-analytics',
        ],
        // Platform account default; no admin panel access.
        'staff' => [],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        foreach (self::ROLES as $role => $permissions) {
            $model = Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

            if ($permissions === '*') {
                $model->syncPermissions(Permission::where('guard_name', 'web')->get());
            } else {
                $model->syncPermissions($permissions);
            }
        }

        $this->assignLegacyRoles();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Give existing admin/staff accounts their spatie role based on the
     * legacy users.role column, without granting admin panel access to
     * accounts that never had it.
     */
    protected function assignLegacyRoles(): void
    {
        $map = [
            'admin' => 'super_admin',
            'ad_manager' => 'ad_manager',
            'manager' => 'manager',
            'finance' => 'finance',
            'support' => 'support',
        ];

        User::query()->chunkById(100, function ($users) use ($map) {
            foreach ($users as $user) {
                $legacy = $user->getRawOriginal('role');
                $role = $map[$legacy] ?? null;

                if ($role && Role::where('name', $role)->where('guard_name', 'web')->exists()) {
                    $user->syncRoles([$role]);
                }
            }
        });
    }
}
