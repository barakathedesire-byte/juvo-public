<?php

namespace Database\Seeders;

use App\Models\Feature;
use App\Models\RoleDecisionPolicy;
use App\Models\StaffDepartment;
use App\Models\SupportSlaPolicy;
use App\Services\Features\FeatureFlagService;
use App\Services\Support\SupportSlaService;
use App\Support\Access\AbilityCatalog;
use App\Support\Access\PermissionCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Centralized staff access seed. Creates the granular permission matrix, the
 * predefined roles, department defaults, per-role decision authority and the
 * maker-checker approval policies. Idempotent: safe to re-run.
 */
class StaffAccessSeeder extends Seeder
{
    /** Requesting a governed action needs approval at/above these amounts. */
    public const APPROVAL_THRESHOLDS = [
        'finance.adjustment.create' => 50000,
        'finance.payout.release' => 200000,
        'finance.refund.approve' => 100000,
        'support.refund.initiate' => 20000,
        'support.compensation.approve' => 0,
    ];

    /**
     * Default feature-registry entries. Keys are what code gates on via
     * feature_enabled('...').
     */
    public const FEATURES = [
        ['key' => 'sales_attribution', 'name' => 'Sales attribution', 'module' => 'sales', 'enabled' => true],
        ['key' => 'sales_agent_network', 'name' => 'Sales agent network', 'module' => 'sales', 'enabled' => true],
        ['key' => 'support_sla', 'name' => 'Support SLA tracking', 'module' => 'support', 'enabled' => true],
        ['key' => 'support_canned_responses', 'name' => 'Canned responses', 'module' => 'support', 'enabled' => true],
        ['key' => 'finance_command_center', 'name' => 'Finance command center', 'module' => 'finance', 'enabled' => true],
        ['key' => 'vendor_wallet', 'name' => 'Vendor wallet', 'module' => 'finance', 'enabled' => true],
        ['key' => 'ad_manager', 'name' => 'Ad manager', 'module' => 'advertising', 'enabled' => true],
    ];

    /**
     * role => granular permission patterns. Patterns support "module.*" and
     * "*.view" globs resolved against the permission catalog.
     */
    public const ROLE_MATRIX = [
        'super_admin' => ['*'],
        'admin' => ['*'],
        'platform_admin' => [
            'dashboard.*', 'vendors.*', 'products.*', 'categories.*', 'collections.*',
            'orders.*', 'shipping.*', 'finance.*', 'transactions.*', 'commissions.*',
            'payouts.*', 'refunds.*', 'disputes.*', 'support.*', 'sales.*',
            'sales_attribution.*', 'sales_agents.*', 'customers.*', 'analytics.*', 'features.*',
            'features_toggle.*', 'page_builder.*', 'settings.*', 'audit.view',
            'approvals.*', 'roles.view', 'ads.*', 'manage-ads',
            'algorithms.*', 'automation.*', 'intelligence.*', 'anomalies.*', 'search.*',
            'pricing.*', 'admin_settings.*', 'terminal.use', 'hr.*',
            'brands.*', 'attributes.*', 'banners.*', 'menus.*', 'social_links.*',
            'product_reviews.*', 'currencies.*', 'integrations.*', 'workflows.*',
            'business_rules.*', 'regions.*', 'product_reports.*',
        ],
        'finance_manager' => [
            'dashboard.view', 'finance.*', 'transactions.*', 'commissions.*',
            'payouts.*', 'refunds.*', 'disputes.*', 'orders.view', 'vendors.view',
            'customers.view', 'analytics.*', 'approvals.*', 'sales_agents.view',
            'sales_agents.payout',
        ],
        'finance_officer' => [
            'dashboard.view', 'finance.view', 'transactions.view', 'transactions.export',
            'commissions.view', 'payouts.view', 'payouts.create', 'payouts.export',
            'refunds.view', 'orders.view', 'vendors.view', 'analytics.view',
            'approvals.view',
        ],
        'finance_approver' => [
            'dashboard.view', 'finance.view', 'transactions.view', 'payouts.view',
            'payouts.approve', 'refunds.view', 'refunds.approve', 'approvals.view',
            'approvals.approve', 'approvals.reject',
        ],
        'vendor_operations_manager' => [
            'dashboard.view', 'vendors.*', 'products.view', 'orders.view',
            'support.*', 'customers.view', 'analytics.view', 'approvals.view',
        ],
        'catalog_manager' => [
            'dashboard.view', 'products.*', 'categories.*', 'collections.*',
            'brands.*', 'attributes.*', 'vendors.view', 'analytics.view',
            'search.view',
        ],
        'order_operations_manager' => [
            'dashboard.view', 'orders.*', 'shipping.*', 'customers.view',
            'refunds.view', 'analytics.view',
        ],
        'support_manager' => [
            'dashboard.view', 'support.*', 'refunds.view', 'refunds.approve',
            'orders.view', 'customers.view', 'disputes.*', 'analytics.view',
            'approvals.*',
        ],
        'support_agent' => [
            'dashboard.view', 'support.view', 'support.reply', 'support.assign',
            'support.close', 'support.escalate', 'orders.view', 'customers.view',
        ],
        'sales_manager' => [
            'dashboard.view', 'sales.*', 'sales_attribution.*', 'sales_agents.*',
            'customers.view', 'orders.view', 'analytics.*', 'approvals.view',
        ],
        'salesperson' => [
            'dashboard.view', 'sales.view', 'sales_agents.view', 'customers.view',
        ],
        'compliance_officer' => [
            'dashboard.view', 'audit.view', 'vendors.view', 'products.view',
            'orders.view', 'finance.view', 'transactions.view', 'refunds.view',
            'approvals.view',
        ],
        'hr_manager' => [
            'dashboard.view', 'hr.view', 'hr.manage', 'hr.manage_org',
            'hr.view_documents', 'hr.upload_documents', 'hr.delete_documents',
            'hr.view_leaves', 'hr.manage_leaves', 'hr.view_attendance',
            'hr.manage_attendance',             'hr.view_recruitment', 'hr.manage_recruitment',
            'hr.view_reports', 'hr.manage_reports',
            'hr.view_compensation', 'hr.manage_compensation',
            'hr.view_bank_details', 'hr.manage_bank_details',
        ],
        'read_only_analyst' => ['*.view'],

        // Legacy roles kept so existing admin accounts keep working.
        'manager' => [
            'dashboard.*', 'vendors.*', 'products.*', 'categories.*', 'collections.*',
            'brands.*', 'attributes.*', 'banners.*', 'menus.*', 'social_links.*',
            'product_reviews.*',
            'orders.*', 'shipping.*', 'customers.*', 'support.*', 'refunds.view',
            'disputes.view', 'analytics.*', 'page_builder.*', 'approvals.view',
            'algorithms.view', 'automation.view', 'automation.run',
            'intelligence.view', 'anomalies.view', 'anomalies.acknowledge',
            'search.*',
            'pricing.view', 'pricing.manage',
            'admin_settings.view', 'admin_settings.manage',
            'hr.view',
        ],
        'finance' => [
            'dashboard.view', 'finance.*', 'transactions.*', 'commissions.*',
            'payouts.*', 'refunds.*', 'orders.view', 'analytics.*', 'approvals.*',
        ],
        'support' => [
            'dashboard.view', 'support.*', 'orders.view', 'customers.view',
            'refunds.view', 'refunds.approve', 'disputes.view', 'analytics.view',
        ],
        'ad_manager' => [
            'dashboard.view', 'features.view', 'analytics.view', 'ads.*', 'manage-ads',
        ],
        'staff' => [],
    ];

    /**
     * role => [ability => mode or [mode, limits]].
     */
    public const DECISION_MATRIX = [
        'finance_manager' => [
            'finance.payout.release' => 'allowed',
            'finance.adjustment.create' => 'allowed',
            'finance.refund.approve' => 'allowed',
            'finance.commission.change' => 'allowed',
            'sales_agents.payout.release' => 'allowed',
        ],
        'finance_officer' => [
            'finance.adjustment.create' => ['limited', ['max_amount' => 100000]],
            'finance.payout.create' => ['limited', ['max_amount' => 500000]],
            'finance.payout.release' => 'prohibited',
            'finance.commission.change' => 'prohibited',
            'finance.refund.approve' => ['limited', ['max_amount' => 50000]],
        ],
        'finance_approver' => [
            'finance.adjustment.create' => 'allowed',
            'finance.payout.release' => 'allowed',
            'finance.refund.approve' => 'allowed',
            'finance.commission.change' => 'requires_approval',
        ],
        'vendor_operations_manager' => [
            'vendor.suspend' => 'requires_approval',
            'vendor.restrict' => 'allowed',
            'product.bulk_publish' => 'allowed',
        ],
        'catalog_manager' => [
            'product.bulk_publish' => 'allowed',
            'finance.adjustment.create' => 'prohibited',
        ],
        'support_agent' => [
            'support.refund.initiate' => 'limited',
            'support.compensation.approve' => 'prohibited',
            'vendor.suspend' => 'prohibited',
        ],
        'support_manager' => [
            'support.refund.initiate' => 'allowed',
            'support.compensation.approve' => 'limited',
            'vendor.suspend' => 'requires_approval',
        ],
        'sales_manager' => [
            'sales.attribution.override' => 'requires_approval',
            'sales_agents.agent.approve' => 'requires_approval',
            'sales_agents.payout.release' => 'requires_approval',
        ],
        'salesperson' => [
            'sales.attribution.override' => 'prohibited',
            'sales_agents.agent.approve' => 'prohibited',
            'sales_agents.payout.release' => 'prohibited',
        ],
        'compliance_officer' => [
            'finance.payout.release' => 'prohibited',
            'vendor.suspend' => 'prohibited',
        ],
        'read_only_analyst' => [
            'finance.adjustment.create' => 'prohibited',
            'finance.payout.release' => 'prohibited',
            'feature.toggle' => 'prohibited',
        ],
    ];

    public const LIMITS = [
        'support_agent' => ['max_amount' => 20000],
        'support_manager' => ['max_amount' => 100000],
    ];

    public const DEPARTMENTS = [
        ['name' => 'Administration', 'slug' => 'administration'],
        ['name' => 'Finance', 'slug' => 'finance'],
        ['name' => 'Vendor Operations', 'slug' => 'vendor-operations'],
        ['name' => 'Catalog', 'slug' => 'catalog'],
        ['name' => 'Order Operations', 'slug' => 'order-operations'],
        ['name' => 'Support', 'slug' => 'support'],
        ['name' => 'Sales', 'slug' => 'sales'],
        ['name' => 'Compliance', 'slug' => 'compliance'],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->seedDepartments();
        $this->seedPermissions();
        $this->seedRoles();
        $this->seedDecisionPolicies();
        $this->seedApprovalPolicies();
        $this->seedSupportSla();
        $this->seedFeatures();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function seedFeatures(): void
    {
        foreach (self::FEATURES as $feature) {
            Feature::updateOrCreate(
                ['key' => $feature['key']],
                [
                    'name' => $feature['name'],
                    'module' => $feature['module'],
                    'is_enabled' => $feature['enabled'],
                ]
            );
        }

        app(FeatureFlagService::class)->flush();
    }

    protected function seedSupportSla(): void
    {
        foreach (SupportSlaService::DEFAULTS as $priority => $targets) {
            SupportSlaPolicy::updateOrCreate(
                ['priority' => $priority],
                [
                    'first_response_minutes' => $targets['first'],
                    'resolution_minutes' => $targets['resolution'],
                    'is_active' => true,
                ]
            );
        }
    }

    protected function seedDepartments(): void
    {
        foreach (self::DEPARTMENTS as $department) {
            StaffDepartment::firstOrCreate(
                ['slug' => $department['slug']],
                ['name' => $department['name'], 'is_active' => true]
            );
        }
    }

    protected function seedPermissions(): void
    {
        $names = array_unique(array_merge(PermissionCatalog::all(), RolesAndPermissionsSeeder::PERMISSIONS));

        foreach ($names as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    }

    protected function seedRoles(): void
    {
        foreach (self::ROLE_MATRIX as $roleName => $patterns) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($this->expand($patterns));
        }
    }

    protected function seedDecisionPolicies(): void
    {
        foreach (self::ROLE_MATRIX as $roleName => $patterns) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if (! $role) {
                continue;
            }

            if (in_array('*', $patterns, true) || in_array($roleName, ['admin', 'super_admin'], true)) {
                foreach (array_keys(AbilityCatalog::all()) as $ability) {
                    RoleDecisionPolicy::updateOrCreate(
                        ['role_id' => $role->id, 'ability' => $ability],
                        ['mode' => RoleDecisionPolicy::MODE_ALLOWED, 'limits' => null]
                    );
                }

                continue;
            }

            $modes = self::DECISION_MATRIX[$roleName] ?? [];

            foreach ($modes as $ability => $mode) {
                if (! AbilityCatalog::get($ability)) {
                    continue;
                }

                $limits = self::LIMITS[$roleName] ?? null;

                if (is_array($mode)) {
                    $limits = $mode[1] ?? null;
                    $mode = $mode[0];
                }

                RoleDecisionPolicy::updateOrCreate(
                    ['role_id' => $role->id, 'ability' => $ability],
                    ['mode' => $mode, 'limits' => $limits]
                );
            }
        }
    }

    protected function seedApprovalPolicies(): void
    {
        foreach (AbilityCatalog::all() as $key => $definition) {
            \App\Models\ApprovalPolicy::updateOrCreate(
                ['key' => $key],
                [
                    'name' => $definition['label'],
                    'module' => $definition['module'],
                    'requires_approval' => true,
                    'min_amount' => self::APPROVAL_THRESHOLDS[$key] ?? 0,
                    'approver_permission' => $definition['approver_permission'],
                    'stages' => 1,
                    'risk' => $definition['risk'],
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Expand patterns like "finance.*" or "*.view" to concrete permission names.
     *
     * @param  array<int, string>  $patterns
     * @return array<int, string>
     */
    protected function expand(array $patterns): array
    {
        $all = PermissionCatalog::all();

        if (in_array('*', $patterns, true)) {
            return Permission::where('guard_name', 'web')->pluck('name')->all();
        }

        $matched = [];

        foreach ($patterns as $pattern) {
            if (! Str::contains($pattern, '*')) {
                $matched[] = $pattern;

                continue;
            }

            foreach ($all as $permission) {
                if (Str::is($pattern, $permission)) {
                    $matched[] = $permission;
                }
            }
        }

        // Always include a role's own legacy capability when present.
        return array_values(array_unique($matched));
    }
}
