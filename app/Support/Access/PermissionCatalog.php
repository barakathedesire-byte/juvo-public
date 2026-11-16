<?php

namespace App\Support\Access;

/**
 * Single source of truth for the granular permission matrix. Each module
 * exposes only the actions that make sense for it. Permission names are
 * "<module>.<action>" and are registered as Spatie permissions on the `web`
 * guard.
 */
class PermissionCatalog
{
    /**
     * module => [label, actions => [action => label]].
     */
    public static function modules(): array
    {
        return [
            'dashboard' => ['label' => 'Dashboard', 'actions' => [
                'view' => 'View dashboard',
            ]],
            'vendors' => ['label' => 'Vendors', 'actions' => [
                'view' => 'View vendors',
                'create' => 'Create vendors',
                'edit' => 'Edit vendors',
                'delete' => 'Delete or archive vendors',
                'approve' => 'Approve vendors',
                'reject' => 'Reject vendors',
                'suspend' => 'Suspend vendors',
                'adjust' => 'Adjust vendor balance',
                'commission' => 'Change vendor commission',
            ]],
            'products' => ['label' => 'Products', 'actions' => [
                'view' => 'View products',
                'create' => 'Create products',
                'edit' => 'Edit products',
                'delete' => 'Delete products',
                'approve' => 'Approve products',
                'reject' => 'Reject products',
                'publish' => 'Publish products',
            ]],
            'categories' => ['label' => 'Categories', 'actions' => [
                'view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete',
            ]],
            'collections' => ['label' => 'Collections', 'actions' => [
                'view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete',
            ]],
            'orders' => ['label' => 'Orders', 'actions' => [
                'view' => 'View orders',
                'edit' => 'Edit orders',
                'cancel' => 'Cancel orders',
                'refund' => 'Refund orders',
                'export' => 'Export orders',
                'delete' => 'Delete orders',
            ]],
            'shipping' => ['label' => 'Shipping', 'actions' => [
                'view' => 'View shipping',
                'edit' => 'Edit shipping',
            ]],
            'finance' => ['label' => 'Finance', 'actions' => [
                'view' => 'View finance',
                'export' => 'Export finance reports',
                'reconcile' => 'Run reconciliation',
            ]],
            'transactions' => ['label' => 'Transactions', 'actions' => [
                'view' => 'View transactions',
                'export' => 'Export transactions',
            ]],
            'commissions' => ['label' => 'Commissions', 'actions' => [
                'view' => 'View commissions',
                'edit' => 'Change commission rules',
            ]],
            'payouts' => ['label' => 'Payouts', 'actions' => [
                'view' => 'View payouts',
                'create' => 'Create payouts',
                'approve' => 'Approve payouts',
                'reject' => 'Reject payouts',
                'release' => 'Release / pay out',
                'export' => 'Export payouts',
            ]],
            'refunds' => ['label' => 'Refunds', 'actions' => [
                'view' => 'View refunds',
                'approve' => 'Approve refunds',
                'reject' => 'Reject refunds',
            ]],
            'disputes' => ['label' => 'Disputes', 'actions' => [
                'view' => 'View disputes',
                'resolve' => 'Resolve disputes',
            ]],
            'support' => ['label' => 'Support', 'actions' => [
                'view' => 'View tickets',
                'reply' => 'Reply to tickets',
                'assign' => 'Assign / reassign tickets',
                'escalate' => 'Escalate tickets',
                'close' => 'Close tickets',
                'reopen' => 'Reopen tickets',
                'export' => 'Export support records',
                'manage_sla' => 'Manage SLA rules',
                'manage_canned' => 'Manage canned responses',
            ]],
            'customers' => ['label' => 'Customers', 'actions' => [
                'view' => 'View customers',
                'create' => 'Create customers',
                'edit' => 'Edit customers',
                'delete' => 'Delete customers',
            ]],
            'analytics' => ['label' => 'Analytics', 'actions' => [
                'view' => 'View analytics',
                'export' => 'Export analytics',
            ]],
            'features_toggle' => ['label' => 'Feature flags', 'actions' => [
                'toggle' => 'Toggle feature flags',
            ]],
            'ads' => ['label' => 'Advertising', 'actions' => [
                'view' => 'View advertising',
                'manage_campaigns' => 'Manage ad campaigns',
                'review' => 'Review & moderate ad submissions',
                'manage_placements' => 'Manage placements & inventory',
                'manage_pricing' => 'Manage ad pricing & packages',
                'manage_finances' => 'Manage ad finances & ledger',
                'manage_optimization' => 'Manage auto-bidding & optimisation',
                'manage_policies' => 'Manage advertising policy',
                'view_auctions' => 'View auction decisions',
                'view_audit' => 'View advertising audit log',
            ]],
            'algorithms' => ['label' => 'Algorithm Engine', 'actions' => [
                'view' => 'View algorithms & configurations',
                'manage' => 'Edit algorithm configuration',
                'activate' => 'Activate / deactivate algorithms',
                'rollback' => 'Roll back algorithm versions',
                'test' => 'Run algorithm test / dry-run',
            ]],
            'automation' => ['label' => 'Automation Engine', 'actions' => [
                'view' => 'View automation workflows & runs',
                'manage' => 'Create & edit automation workflows',
                'run' => 'Run automation workflows',
            ]],
            'intelligence' => ['label' => 'Marketplace Intelligence', 'actions' => [
                'view' => 'View marketplace intelligence',
                'manage' => 'Manage metric snapshots & rules',
                'export' => 'Export intelligence reports',
            ]],
            'anomalies' => ['label' => 'Anomaly Detection', 'actions' => [
                'view' => 'View anomalies',
                'manage' => 'Configure anomaly rules',
                'acknowledge' => 'Acknowledge & assign anomalies',
                'resolve' => 'Resolve / dismiss anomalies',
            ]],
            'search' => ['label' => 'Search Engine', 'actions' => [
                'view' => 'View search results',
                'manage' => 'Manage search synonyms',
                'reindex' => 'Rebuild the search index',
            ]],
            'pricing' => ['label' => 'Pricing & Promotions', 'actions' => [
                'view' => 'View pricing & promotions',
                'manage' => 'Create & edit promotions',
                'approve' => 'Approve promotions',
                'override' => 'Override margin guardrails',
            ]],
            'admin_settings' => ['label' => 'Admin Portal Settings', 'actions' => [
                'view' => 'View admin portal settings',
                'manage' => 'Manage admin portal appearance, tabs & terminal',
            ]],
            'brands' => ['label' => 'Brands', 'actions' => [
                'view' => 'View brands', 'create' => 'Create brands', 'edit' => 'Edit brands', 'delete' => 'Delete brands',
            ]],
            'attributes' => ['label' => 'Attributes', 'actions' => [
                'view' => 'View attributes', 'create' => 'Create attributes', 'edit' => 'Edit attributes', 'delete' => 'Delete attributes',
            ]],
            'banners' => ['label' => 'Banners', 'actions' => [
                'view' => 'View banners', 'create' => 'Create banners', 'edit' => 'Edit banners', 'delete' => 'Delete banners',
            ]],
            'menus' => ['label' => 'Menus & menu items', 'actions' => [
                'view' => 'View menus and items', 'create' => 'Create menus and items', 'edit' => 'Edit menus and items', 'delete' => 'Delete menus and items',
            ]],
            'social_links' => ['label' => 'Social media links', 'actions' => [
                'view' => 'View social links', 'create' => 'Create social links', 'edit' => 'Edit social links', 'delete' => 'Delete social links',
            ]],
            'product_reviews' => ['label' => 'Product reviews', 'actions' => [
                'view' => 'View product reviews', 'edit' => 'Moderate product reviews', 'delete' => 'Delete product reviews',
            ]],
            'currencies' => ['label' => 'Currencies', 'actions' => [
                'view' => 'View currencies', 'edit' => 'Manage currencies',
            ]],
            'integrations' => ['label' => 'Integrations', 'actions' => [
                'view' => 'View integrations', 'edit' => 'Configure integrations',
            ]],
            'workflows' => ['label' => 'Workflows', 'actions' => [
                'view' => 'View workflows', 'edit' => 'Edit workflows',
            ]],
            'business_rules' => ['label' => 'Business rules', 'actions' => [
                'view' => 'View business rules', 'edit' => 'Edit business rules',
            ]],
            'regions' => ['label' => 'Regions & locations', 'actions' => [
                'view' => 'View regions', 'create' => 'Create regions', 'edit' => 'Edit regions', 'delete' => 'Delete regions',
            ]],
            'product_reports' => ['label' => 'Product reports', 'actions' => [
                'view' => 'View product reports', 'edit' => 'Handle product reports',
            ]],
            'audit' => ['label' => 'Audit log', 'actions' => [
                'view' => 'View audit log',
            ]],
            'approvals' => ['label' => 'Approvals', 'actions' => [
                'view' => 'View approvals', 'request' => 'Request approval', 'approve' => 'Approve requests',
                'reject' => 'Reject requests', 'escalate' => 'Escalate approvals', 'reassign' => 'Reassign approvals',
            ]],
            'features' => ['label' => 'Feature flags', 'actions' => [
                'view' => 'View feature flags', 'create' => 'Create feature flags', 'edit' => 'Edit feature flags',
                'delete' => 'Delete feature flags', 'toggle' => 'Toggle feature flags',
            ]],
            'page_builder' => ['label' => 'Page builder', 'actions' => [
                'view' => 'View pages', 'edit' => 'Edit pages', 'publish' => 'Publish pages',
            ]],
            'roles' => ['label' => 'Roles & permissions', 'actions' => [
                'view' => 'View roles', 'create' => 'Create roles', 'edit' => 'Edit roles', 'delete' => 'Delete roles',
            ]],
            'sales' => ['label' => 'Sales', 'actions' => [
                'view' => 'View sales',
            ]],
            'sales_attribution' => ['label' => 'Sales attribution', 'actions' => [
                'view' => 'View sales attribution', 'manage' => 'Manage sales attribution',
            ]],
            'sales_agents' => ['label' => 'Sales agents', 'actions' => [
                'view' => 'View sales agents', 'manage' => 'Manage sales agents', 'payout' => 'Create agent payouts',
            ]],
            'settings' => ['label' => 'Settings', 'actions' => [
                'view' => 'View settings', 'edit' => 'Edit settings',
            ]],
            'staff' => ['label' => 'Staff', 'actions' => [
                'view' => 'View staff', 'create' => 'Create staff', 'edit' => 'Edit staff',
                'delete' => 'Delete staff', 'manage_access' => 'Manage staff access',
            ]],
            'hr' => ['label' => 'Human resources', 'actions' => [
                'view' => 'View HR', 'manage' => 'Manage HR', 'manage_org' => 'Manage organisation',
                'view_documents' => 'View documents', 'upload_documents' => 'Upload documents', 'delete_documents' => 'Delete documents',
                'view_leaves' => 'View leaves', 'manage_leaves' => 'Manage leaves',
                'view_attendance' => 'View attendance', 'manage_attendance' => 'Manage attendance',
                'view_recruitment' => 'View recruitment', 'manage_recruitment' => 'Manage recruitment',
                'view_reports' => 'View HR reports', 'manage_reports' => 'Manage HR reports',
                'view_compensation' => 'View compensation', 'manage_compensation' => 'Manage compensation',
                'view_bank_details' => 'View bank details', 'manage_bank_details' => 'Manage bank details',
            ]],
            'terminal' => ['label' => 'Terminal', 'actions' => [
                'use' => 'Use the admin terminal',
            ]],
        ];
    }

    /**
     * Flat list of permission names.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        $permissions = [];

        foreach (static::modules() as $module => $meta) {
            foreach (array_keys($meta['actions']) as $action) {
                $permissions[] = $module.'.'.$action;
            }
        }

        return $permissions;
    }

    public static function labelFor(string $permission): string
    {
        [$module, $action] = array_pad(explode('.', $permission, 2), 2, '');

        $moduleLabel = static::modules()[$module]['label'] ?? ucfirst($module);
        $actionLabel = static::modules()[$module]['actions'][$action] ?? $action;

        return $moduleLabel.' — '.$actionLabel;
    }

    /**
     * Legacy coarse permissions that existing routes still enforce. Kept so we
     * never silently break already-authorised areas.
     *
     * @return array<int, string>
     */
    public static function legacy(): array
    {
        return [
            'manage-settings', 'manage-vendors', 'manage-products', 'manage-orders',
            'manage-finances', 'manage-ads', 'manage-homepage', 'manage-customers',
            'manage-reviews', 'manage-content', 'manage-users', 'manage-support',
            'view-analytics',
        ];
    }
}
