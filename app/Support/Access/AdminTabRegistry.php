<?php

namespace App\Support\Access;

use App\Models\User;

/**
 * Read-only inventory of the code-defined (built-in) admin portal tabs, used
 * by the Admin Settings tab manager to show every tab the portal ships with.
 * Built-in tabs are managed in code; the settings UI lists them for visibility
 * and to let administrators see what exists before adding custom tabs.
 *
 * It is also the single place that maps a tab to the permissions required to
 * see it and to act inside it, so the sidebar enforces the permission matrix in
 * one definition instead of hardcoding ability lists in the view. Navigation is
 * a user-experience concern: every route a tab points to is independently
 * enforced on the server.
 */
class AdminTabRegistry
{
    /**
     * tab key => permissions required to see it and to change anything in it.
     *
     * A role granted only the "see" abilities gets a view-only tab: the tab is
     * visible but every create/edit control inside it stays hidden.
     *
     * @return array<string, array{see: array<int, string>, write: array<int, string>}>
     */
    public static function permissions(): array
    {
        return [
            'dashboard' => ['see' => ['dashboard.view'], 'write' => []],
            'command-center' => ['see' => ['analytics.view', 'dashboard.view', 'finance.view', 'sales.view', 'support.view', 'orders.view', 'approvals.view', 'audit.view', 'features.view'], 'write' => []],
            'reports' => ['see' => ['analytics.view', 'finance.view', 'sales.view', 'manage-finances', 'view-analytics'], 'write' => []],
            'products' => ['see' => ['products.view', 'manage-products'], 'write' => ['products.create', 'products.edit', 'products.delete', 'products.approve', 'manage-products']],
            'categories' => ['see' => ['categories.view'], 'write' => ['categories.create', 'categories.edit', 'categories.delete']],
            'brands' => ['see' => ['brands.view'], 'write' => ['brands.create', 'brands.edit', 'brands.delete']],
            'attributes' => ['see' => ['attributes.view'], 'write' => ['attributes.create', 'attributes.edit', 'attributes.delete']],
            'customers' => ['see' => ['customers.view', 'manage-customers'], 'write' => ['customers.create', 'customers.edit', 'customers.delete', 'manage-customers']],
            'vendors' => ['see' => ['vendors.view', 'manage-vendors'], 'write' => ['vendors.create', 'vendors.edit', 'vendors.delete', 'vendors.approve', 'vendors.suspend', 'manage-vendors']],
            'orders' => ['see' => ['orders.view', 'manage-orders'], 'write' => ['orders.edit', 'orders.cancel', 'orders.refund', 'orders.delete', 'manage-orders']],
            'payments' => ['see' => ['finance.view', 'manage-finances'], 'write' => ['finance.reconcile', 'manage-finances']],
            'refunds' => ['see' => ['refunds.view', 'manage-finances'], 'write' => ['refunds.approve', 'refunds.reject', 'manage-finances']],
            'support' => ['see' => ['support.view', 'manage-support'], 'write' => ['support.reply', 'support.assign', 'support.escalate', 'support.close', 'support.reopen', 'support.manage_sla', 'support.manage_canned', 'manage-support']],
            'finance' => ['see' => ['finance.view'], 'write' => ['finance.reconcile']],
            'payouts' => ['see' => ['payouts.view'], 'write' => ['payouts.create', 'payouts.approve', 'payouts.reject', 'payouts.release']],
            'ad-manager' => ['see' => ['ads.view', 'manage-ads'], 'write' => ['ads.manage_campaigns', 'ads.review', 'ads.manage_placements', 'ads.manage_pricing', 'ads.manage_finances', 'ads.manage_optimization', 'ads.manage_policies', 'manage-ads']],
            'homepage' => ['see' => ['page_builder.view', 'settings.view', 'manage-homepage'], 'write' => ['page_builder.edit', 'settings.edit', 'manage-homepage']],
            'banners' => ['see' => ['banners.view', 'page_builder.view'], 'write' => ['banners.create', 'banners.edit', 'banners.delete', 'page_builder.edit']],
            'product-reviews' => ['see' => ['product_reviews.view', 'manage-reviews'], 'write' => ['product_reviews.edit', 'product_reviews.delete', 'manage-reviews']],
            'pricing-marketing' => ['see' => ['settings.view', 'pricing.view', 'manage-settings'], 'write' => ['settings.edit', 'pricing.manage', 'manage-settings']],
            'pages' => ['see' => ['page_builder.view', 'manage-content'], 'write' => ['page_builder.edit', 'page_builder.publish', 'manage-content']],
            'menus' => ['see' => ['menus.view', 'page_builder.view'], 'write' => ['menus.create', 'menus.edit', 'menus.delete', 'page_builder.edit']],
            'menu-items' => ['see' => ['menus.view', 'page_builder.view'], 'write' => ['menus.create', 'menus.edit', 'menus.delete', 'page_builder.edit']],
            'social-media-links' => ['see' => ['social_links.view', 'page_builder.view'], 'write' => ['social_links.create', 'social_links.edit', 'social_links.delete', 'page_builder.edit']],
            'algorithms' => ['see' => ['algorithms.view'], 'write' => ['algorithms.manage', 'algorithms.activate', 'algorithms.rollback', 'algorithms.test']],
            'automation' => ['see' => ['automation.view'], 'write' => ['automation.manage', 'automation.run']],
            'intelligence' => ['see' => ['intelligence.view'], 'write' => ['intelligence.manage']],
            'search' => ['see' => ['search.view'], 'write' => ['search.manage', 'search.reindex']],
            'pricing' => ['see' => ['pricing.view'], 'write' => ['pricing.manage', 'pricing.approve', 'pricing.override']],
            'staff' => ['see' => ['staff.view'], 'write' => ['staff.create', 'staff.edit', 'staff.delete', 'staff.manage_access']],
            'roles' => ['see' => ['roles.view'], 'write' => ['roles.create', 'roles.edit', 'roles.delete', 'roles.manage_permissions']],
            'approvals' => ['see' => ['approvals.view'], 'write' => ['approvals.approve', 'approvals.reject']],
            'audit-logs' => ['see' => ['audit.view'], 'write' => []],
            'features' => ['see' => ['features.view'], 'write' => ['features.create', 'features.edit', 'features.delete', 'features.toggle', 'features_toggle.toggle']],
            'integrity' => ['see' => ['audit.view', 'finance.view', 'finance.reconcile'], 'write' => []],
            'site-settings' => ['see' => ['settings.view', 'manage-settings'], 'write' => ['settings.edit', 'manage-settings']],
            'currencies' => ['see' => ['currencies.view', 'settings.view'], 'write' => ['currencies.edit', 'settings.edit']],
            'payment-gateways' => ['see' => ['finance.view', 'settings.view', 'manage-finances'], 'write' => ['settings.edit', 'manage-finances']],
            'integrations' => ['see' => ['integrations.view', 'settings.view'], 'write' => ['integrations.edit', 'settings.edit']],
            'workflows' => ['see' => ['workflows.view', 'settings.view'], 'write' => ['workflows.edit', 'settings.edit']],
            'business-rules' => ['see' => ['business_rules.view', 'settings.view'], 'write' => ['business_rules.edit', 'settings.edit']],
            'regions' => ['see' => ['regions.view', 'settings.view'], 'write' => ['regions.create', 'regions.edit', 'regions.delete', 'settings.edit']],
            'product-reports' => ['see' => ['product_reports.view'], 'write' => ['product_reports.edit']],
            'admin-settings' => ['see' => ['admin_settings.view'], 'write' => ['admin_settings.manage']],
        ];
    }

    /**
     * Whether the user may see this tab at all.
     */
    public static function canSee(string $key, ?User $user = null): bool
    {
        $user = $user ?? auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        $abilities = static::permissions()[$key]['see'] ?? [];

        return $abilities !== [] && $user->canAny($abilities);
    }

    /**
     * Whether the user may change anything inside this tab. When false the tab
     * is view-only: every create/edit control inside it must be hidden.
     */
    public static function canWrite(string $key, ?User $user = null): bool
    {
        $user = $user ?? auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        $abilities = static::permissions()[$key]['write'] ?? [];

        return $abilities !== [] && $user->canAny($abilities);
    }

    /**
     * Whether the user may see at least one of the given tabs. Used to hide a
     * section header whose whole section is hidden for that user.
     *
     * @param  array<int, string>  $keys
     */
    public static function canSeeAny(array $keys, ?User $user = null): bool
    {
        foreach ($keys as $key) {
            if (static::canSee($key, $user)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array{key:string,label:string,group:string,icon:string,source:string}>
     */
    public static function builtIn(): array
    {
        return [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'group' => 'Overview', 'icon' => 'fas fa-home', 'source' => 'core'],
            ['key' => 'command-center', 'label' => 'Command Center', 'group' => 'Overview', 'icon' => 'fas fa-gauge-high', 'source' => 'core'],
            ['key' => 'reports', 'label' => 'Sales Reports', 'group' => 'Overview', 'icon' => 'fas fa-chart-line', 'source' => 'core'],
            ['key' => 'products', 'label' => 'Products', 'group' => 'Catalog', 'icon' => 'fas fa-box', 'source' => 'core'],
            ['key' => 'categories', 'label' => 'Categories', 'group' => 'Catalog', 'icon' => 'fas fa-th-large', 'source' => 'core'],
            ['key' => 'brands', 'label' => 'Brands', 'group' => 'Catalog', 'icon' => 'fas fa-tags', 'source' => 'core'],
            ['key' => 'attributes', 'label' => 'Attributes', 'group' => 'Catalog', 'icon' => 'fas fa-cogs', 'source' => 'core'],
            ['key' => 'customers', 'label' => 'Customers', 'group' => 'Customers & Vendors', 'icon' => 'fas fa-users', 'source' => 'core'],
            ['key' => 'vendors', 'label' => 'Vendors', 'group' => 'Customers & Vendors', 'icon' => 'fas fa-user-tag', 'source' => 'core'],
            ['key' => 'orders', 'label' => 'Orders', 'group' => 'Sales & Operations', 'icon' => 'fas fa-shopping-cart', 'source' => 'core'],
            ['key' => 'payments', 'label' => 'Payments', 'group' => 'Sales & Operations', 'icon' => 'fas fa-credit-card', 'source' => 'core'],
            ['key' => 'refunds', 'label' => 'Refunds', 'group' => 'Sales & Operations', 'icon' => 'fas fa-undo', 'source' => 'core'],
            ['key' => 'support', 'label' => 'Support Tickets', 'group' => 'Sales & Operations', 'icon' => 'fas fa-headset', 'source' => 'core'],
            ['key' => 'finance', 'label' => 'Finance Command Center', 'group' => 'Sales & Operations', 'icon' => 'fas fa-sack-dollar', 'source' => 'core'],
            ['key' => 'payouts', 'label' => 'Vendor Payouts', 'group' => 'Sales & Operations', 'icon' => 'fas fa-money-bill-transfer', 'source' => 'core'],
            ['key' => 'ad-manager', 'label' => 'Ad Manager', 'group' => 'Marketing & Content', 'icon' => 'fas fa-bullhorn', 'source' => 'core'],
            ['key' => 'homepage', 'label' => 'Homepage Builder', 'group' => 'Marketing & Content', 'icon' => 'fas fa-house', 'source' => 'core'],
            ['key' => 'banners', 'label' => 'Banners', 'group' => 'Marketing & Content', 'icon' => 'fas fa-image', 'source' => 'core'],
            ['key' => 'product-reviews', 'label' => 'Product Reviews', 'group' => 'Marketing & Content', 'icon' => 'fas fa-star', 'source' => 'core'],
            ['key' => 'pricing-marketing', 'label' => 'Pricing & Marketing', 'group' => 'Marketing & Content', 'icon' => 'fas fa-tags', 'source' => 'core'],
            ['key' => 'pages', 'label' => 'Pages', 'group' => 'Marketing & Content', 'icon' => 'fas fa-file-alt', 'source' => 'core'],
            ['key' => 'menus', 'label' => 'Menus', 'group' => 'Marketing & Content', 'icon' => 'fas fa-bars', 'source' => 'core'],
            ['key' => 'social-media-links', 'label' => 'Social Media Links', 'group' => 'Marketing & Content', 'icon' => 'fas fa-link', 'source' => 'core'],
            ['key' => 'algorithms', 'label' => 'Algorithm Engine', 'group' => 'Intelligence', 'icon' => 'fas fa-microchip', 'source' => 'core'],
            ['key' => 'automation', 'label' => 'Automation Engine', 'group' => 'Intelligence', 'icon' => 'fas fa-robot', 'source' => 'core'],
            ['key' => 'intelligence', 'label' => 'Marketplace Intelligence', 'group' => 'Intelligence', 'icon' => 'fas fa-chart-pie', 'source' => 'core'],
            ['key' => 'search', 'label' => 'Search Engine', 'group' => 'Intelligence', 'icon' => 'fas fa-magnifying-glass-chart', 'source' => 'core'],
            ['key' => 'pricing', 'label' => 'Pricing & Promotions', 'group' => 'Intelligence', 'icon' => 'fas fa-tags', 'source' => 'core'],
            ['key' => 'staff', 'label' => 'Staff & Roles', 'group' => 'Governance', 'icon' => 'fas fa-user-shield', 'source' => 'core'],
            ['key' => 'roles', 'label' => 'Roles & Permissions', 'group' => 'Governance', 'icon' => 'fas fa-shield-halved', 'source' => 'core'],
            ['key' => 'approvals', 'label' => 'Approvals', 'group' => 'Governance', 'icon' => 'fas fa-clipboard-check', 'source' => 'core'],
            ['key' => 'audit-logs', 'label' => 'Audit Logs', 'group' => 'Governance', 'icon' => 'fas fa-clipboard-list', 'source' => 'core'],
            ['key' => 'features', 'label' => 'Features', 'group' => 'Governance', 'icon' => 'fas fa-toggle-on', 'source' => 'core'],
            ['key' => 'integrity', 'label' => 'Integrity', 'group' => 'Governance', 'icon' => 'fas fa-scale-balanced', 'source' => 'core'],
            ['key' => 'site-settings', 'label' => 'Site Settings', 'group' => 'System', 'icon' => 'fas fa-cog', 'source' => 'core'],
            ['key' => 'currencies', 'label' => 'Currencies', 'group' => 'System', 'icon' => 'fas fa-dollar-sign', 'source' => 'core'],
            ['key' => 'payment-gateways', 'label' => 'Payment Gateways', 'group' => 'System', 'icon' => 'fas fa-cogs', 'source' => 'core'],
            ['key' => 'integrations', 'label' => 'Integrations', 'group' => 'System', 'icon' => 'fas fa-plug', 'source' => 'core'],
            ['key' => 'workflows', 'label' => 'Workflows', 'group' => 'System', 'icon' => 'fas fa-diagram-project', 'source' => 'core'],
            ['key' => 'business-rules', 'label' => 'Business Rules', 'group' => 'System', 'icon' => 'fas fa-scale-balanced', 'source' => 'core'],
            ['key' => 'admin-settings', 'label' => 'Admin Settings', 'group' => 'System', 'icon' => 'fas fa-sliders', 'source' => 'core'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function groups(): array
    {
        return array_values(array_unique(array_column(static::builtIn(), 'group')));
    }
}
