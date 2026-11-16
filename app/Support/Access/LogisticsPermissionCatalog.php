<?php

namespace App\Support\Access;

/**
 * Single source of truth for logistics portal modules and the default module
 * access granted to each logistics role.
 *
 * Explicit per-account permissions are additive and use the same
 * "logistics.<module>" names, so admin-created profiles are validated against
 * this catalog instead of accepting arbitrary permission strings.
 */
class LogisticsPermissionCatalog
{
    public const MODULES = [
        'dashboard' => 'Dashboard',
        'orders' => 'Orders & delivery',
        'dispatch' => 'Dispatch',
        'tracking' => 'Live tracking',
        'riders' => 'Riders',
        'finance' => 'Cash & finance',
        'payouts' => 'Rider payouts',
        'zones' => 'Zones, hubs & pricing',
        'analytics' => 'Analytics & reports',
        'reports' => 'Reports',
        'support' => 'Support',
        'fraud' => 'Fraud & risk',
        'notifications' => 'Notifications',
        'settings' => 'Settings & integrations',
    ];

    /**
     * role => modules granted by default. A literal "*" grants every module.
     */
    public const ROLE_MODULES = [
        'super_admin' => ['*'],
        'admin' => ['*'],
        'dispatcher' => [
            'dashboard', 'orders', 'dispatch', 'tracking', 'riders', 'zones', 'analytics',
        ],
        'support' => [
            'dashboard', 'orders', 'support', 'notifications', 'analytics', 'fraud',
        ],
    ];

    /**
     * @return array<int, string>
     */
    public static function modules(): array
    {
        return array_keys(self::MODULES);
    }

    /**
     * @return array<int, string>
     */
    public static function permissions(): array
    {
        return array_map(fn (string $module) => self::permission($module), self::modules());
    }

    public static function permission(string $module): string
    {
        return 'logistics.'.$module;
    }

    public static function moduleFromPermission(string $permission): ?string
    {
        if (! str_starts_with($permission, 'logistics.')) {
            return null;
        }

        $module = substr($permission, strlen('logistics.'));

        return in_array($module, self::modules(), true) ? $module : null;
    }

    /**
     * @return array<int, string>
     */
    public static function modulesForRole(?string $role): array
    {
        $modules = self::ROLE_MODULES[$role] ?? [];

        return in_array('*', $modules, true) ? self::modules() : $modules;
    }

    public static function roleCanAccess(?string $role, string $module): bool
    {
        return in_array($module, self::modulesForRole($role), true);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::MODULES as $module => $label) {
            $options[self::permission($module)] = $label;
        }

        return $options;
    }
}
