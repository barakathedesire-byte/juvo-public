<?php

namespace App\Support\Access;

/**
 * Governed abilities: the sensitive actions that are subject to decision
 * authority (allowed / limited / requires approval / prohibited) and, where
 * configured, maker-checker approval policies.
 *
 * The `permission` is the granular permission needed to act at all; the
 * `approver_permission` is required to approve someone else's request.
 */
class AbilityCatalog
{
    /**
     * ability => definition.
     */
    public static function all(): array
    {
        return [
            'finance.adjustment.create' => [
                'label' => 'Create financial adjustment',
                'module' => 'finance',
                'permission' => 'vendors.adjust',
                'approver_permission' => 'finance.reconcile',
                'limit_key' => 'max_amount',
                'risk' => 'high',
            ],
            'finance.payout.create' => [
                'label' => 'Create vendor payout',
                'module' => 'finance',
                'permission' => 'payouts.create',
                'approver_permission' => 'payouts.approve',
                'limit_key' => 'max_amount',
                'risk' => 'medium',
            ],
            'finance.payout.release' => [
                'label' => 'Release / pay out',
                'module' => 'finance',
                'permission' => 'payouts.release',
                'approver_permission' => 'payouts.approve',
                'limit_key' => 'max_amount',
                'risk' => 'high',
            ],
            'finance.commission.change' => [
                'label' => 'Change commission rules',
                'module' => 'finance',
                'permission' => 'commissions.edit',
                'approver_permission' => 'finance.reconcile',
                'limit_key' => null,
                'risk' => 'high',
            ],
            'finance.refund.approve' => [
                'label' => 'Approve refund',
                'module' => 'refunds',
                'permission' => 'refunds.approve',
                'approver_permission' => 'refunds.approve',
                'limit_key' => 'max_amount',
                'risk' => 'high',
            ],
            'support.refund.initiate' => [
                'label' => 'Initiate refund from support',
                'module' => 'support',
                'permission' => 'support.reply',
                'approver_permission' => 'refunds.approve',
                'limit_key' => 'max_amount',
                'risk' => 'medium',
            ],
            'support.compensation.approve' => [
                'label' => 'Approve exceptional compensation',
                'module' => 'support',
                'permission' => 'support.close',
                'approver_permission' => 'refunds.approve',
                'limit_key' => 'max_amount',
                'risk' => 'high',
            ],
            'vendor.suspend' => [
                'label' => 'Suspend vendor',
                'module' => 'vendors',
                'permission' => 'vendors.suspend',
                'approver_permission' => 'vendors.approve',
                'limit_key' => null,
                'risk' => 'high',
            ],
            'vendor.restrict' => [
                'label' => 'Restrict vendor access',
                'module' => 'vendors',
                'permission' => 'vendors.suspend',
                'approver_permission' => 'vendors.approve',
                'limit_key' => null,
                'risk' => 'medium',
            ],
            'product.bulk_publish' => [
                'label' => 'Bulk publish products',
                'module' => 'products',
                'permission' => 'products.publish',
                'approver_permission' => 'products.approve',
                'limit_key' => null,
                'risk' => 'medium',
            ],
            'discount.large' => [
                'label' => 'Apply large discount',
                'module' => 'orders',
                'permission' => 'orders.edit',
                'approver_permission' => 'orders.edit',
                'limit_key' => 'max_discount_percent',
                'risk' => 'medium',
            ],
            'pricing.discount.override' => [
                'label' => 'Override pricing margin guardrail',
                'module' => 'pricing',
                'permission' => 'pricing.override',
                'approver_permission' => 'pricing.approve',
                'limit_key' => 'max_discount_percent',
                'risk' => 'high',
            ],
            'staff.role.change' => [
                'label' => 'Change staff role',
                'module' => 'staff',
                'permission' => 'staff.manage_access',
                'approver_permission' => 'roles.manage_permissions',
                'limit_key' => null,
                'risk' => 'high',
            ],
            'staff.permission.change' => [
                'label' => 'Change role permissions',
                'module' => 'roles',
                'permission' => 'roles.manage_permissions',
                'approver_permission' => 'roles.manage_permissions',
                'limit_key' => null,
                'risk' => 'high',
            ],
            'settings.sensitive.change' => [
                'label' => 'Change sensitive settings',
                'module' => 'settings',
                'permission' => 'settings.edit',
                'approver_permission' => 'settings.edit',
                'limit_key' => null,
                'risk' => 'high',
            ],
            'feature.toggle' => [
                'label' => 'Toggle a feature',
                'module' => 'features',
                'permission' => 'features.toggle',
                'approver_permission' => 'features.edit',
                'limit_key' => null,
                'risk' => 'medium',
            ],
        ];
    }

    public static function get(string $ability): ?array
    {
        return static::all()[$ability] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(static::all());
    }
}
