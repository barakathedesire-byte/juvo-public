<?php

namespace App\Support\Access;

use App\Models\User;

/**
 * Centralized navigation metadata. The staff portal navigation and workspace
 * landing are generated from this single definition, so a workspace only
 * appears when the authenticated staff member holds the required permission and
 * any feature flag is enabled. This is the single place that maps permissions
 * to navigation; components do not hardcode their own permission lists.
 *
 * Navigation is a user-experience concern only. Every route it points to is
 * independently enforced on the server (permission middleware + the
 * AuthorizationService).
 */
class NavigationRegistry
{
    /**
     * @return array<int, array{key:string,label:string,route:string,icon:string,permission:string|array<int,string>,feature:?string,active:string}>
     */
    public static function items(): array
    {
        return [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => 'fas fa-home', 'permission' => 'dashboard.view', 'feature' => null, 'active' => 'admin.dashboard'],
            ['label' => 'Command Center', 'route' => 'admin.command-center.index', 'icon' => 'fas fa-gauge-high', 'permission' => ['analytics.view', 'dashboard.view', 'finance.view', 'sales.view', 'support.view', 'orders.view', 'approvals.view', 'audit.view', 'features.view'], 'active' => 'admin.command-center.index'],
            ['label' => 'Intelligence Command Center', 'route' => 'admin.command-center.intelligence', 'icon' => 'fas fa-chart-pie', 'permission' => ['analytics.view', 'dashboard.view', 'intelligence.view', 'orders.view', 'support.view', 'vendors.view', 'customers.view'], 'active' => 'admin.command-center.intelligence'],

            ['label' => 'Finance', 'route' => 'admin.finance.index', 'icon' => 'fas fa-sack-dollar', 'permission' => 'finance.view', 'active' => 'admin.finance'],
            ['label' => 'Vendor Payouts', 'route' => 'admin.payouts.index', 'icon' => 'fas fa-money-bill-transfer', 'permission' => 'payouts.view', 'group' => 'finance'],
            ['label' => 'Orders', 'route' => 'admin.orders.index', 'icon' => 'fas fa-shopping-cart', 'permission' => 'orders.view'],
        ];
    }

    /**
     * Top-level workspaces the given user may enter, used for landing and the
     * access-denied page. Driven purely by effective permissions.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function workspaces($user): array
    {
        if (! $user instanceof \App\Models\User) {
            return [];
        }

        $definitions = [
            ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => 'fa-home', 'permission' => 'dashboard.view'],
            ['label' => 'Command Center', 'route' => 'admin.command-center.index', 'icon' => 'fa-gauge-high', 'permission' => 'analytics.view'],
            ['label' => 'Intelligence Command Center', 'route' => 'admin.command-center.intelligence', 'icon' => 'fa-chart-pie', 'permission' => 'analytics.view'],
            ['label' => 'Finance', 'route' => 'admin.finance.index', 'icon' => 'fa-sack-dollar', 'permission' => 'finance.view'],
            ['label' => 'Orders & Fulfillment', 'route' => 'admin.orders.index', 'icon' => 'fa-shopping-cart', 'permission' => 'orders.view'],
            ['label' => 'Support', 'route' => 'admin.support-tickets.index', 'icon' => 'fa-headset', 'permission' => 'support.view'],
            ['label' => 'Vendor Operations', 'route' => 'admin.vendors.index', 'icon' => 'fa-user-tag', 'permission' => 'vendors.view'],
            ['label' => 'Product & Catalog', 'route' => 'admin.products.index', 'icon' => 'fa-box', 'permission' => 'products.view'],
            ['label' => 'Customers', 'route' => 'admin.customers.index', 'icon' => 'fa-users', 'permission' => 'customers.view'],
            ['label' => 'Approvals', 'route' => 'admin.approvals.index', 'icon' => 'fa-clipboard-check', 'permission' => 'approvals.view'],
            ['label' => 'Algorithm Engine', 'route' => 'admin.algorithms.index', 'icon' => 'fa-microchip', 'permission' => 'algorithms.view'],
            ['label' => 'Automation Engine', 'route' => 'admin.automation.index', 'icon' => 'fa-robot', 'permission' => 'automation.view'],
            ['label' => 'Marketplace Intelligence', 'route' => 'admin.intelligence.index', 'icon' => 'fa-chart-pie', 'permission' => 'intelligence.view'],
            ['label' => 'Search Engine', 'route' => 'admin.search.index', 'icon' => 'fa-magnifying-glass-chart', 'permission' => 'search.view'],
            ['label' => 'Pricing & Promotions', 'route' => 'admin.pricing.index', 'icon' => 'fa-tags', 'permission' => 'pricing.view'],
            ['label' => 'Staff &amp; Roles', 'route' => 'admin.staff.index', 'icon' => 'fa-user-shield', 'permission' => 'staff.view'],
            ['label' => 'Features', 'route' => 'admin.features.index', 'icon' => 'fa-toggle-on', 'permission' => 'features.view'],
            ['label' => 'Audit Logs', 'route' => 'admin.audit-logs.index', 'icon' => 'fa-clipboard-list', 'permission' => 'audit.view'],
        ];

        $allowed = [];

        foreach ($definitions as $definition) {
            if ($user->can($definition['permission'])) {
                $allowed[] = $definition;
            }
        }

        return $allowed;
    }
}
