<?php

namespace App\Services\Access;

use App\Models\User;
use App\Services\Admin\AuditLogger;
use App\Services\Admin\DecisionAuthority;
use App\Support\Access\AccessScope;
use App\Support\Access\AccountStatus;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * The single authorization engine for the admin portal. Every protected
 * backend operation should resolve access through this service so the decision
 * is always: authenticated → active → approved for portal → permitted →
 * in scope → (optionally) approval-governed.
 *
 * Deny-by-default: a missing/unresolvable permission denies the operation.
 * This is the server-side source of truth; frontend checks are UX only.
 */
class AuthorizationService
{
    /**
     * Permission => route that forms a usable landing workspace, most specific
     * first. Used to compute the post-login redirect for a staff member.
     */
    protected const LANDING_PRIORITY = [
        ['permission' => 'finance.view', 'route' => 'admin.finance.index'],
        ['permission' => 'analytics.view', 'route' => 'admin.command-center.index'],
        ['permission' => 'orders.view', 'route' => 'admin.orders.index'],
        ['permission' => 'support.view', 'route' => 'admin.support-tickets.index'],
        ['permission' => 'vendors.view', 'route' => 'admin.vendors.index'],
        ['permission' => 'products.view', 'route' => 'admin.products.index'],
        ['permission' => 'customers.view', 'route' => 'admin.customers.index'],
        ['permission' => 'approvals.view', 'route' => 'admin.approvals.index'],
        ['permission' => 'audit.view', 'route' => 'admin.audit-logs.index'],
        ['permission' => 'features.view', 'route' => 'admin.features.index'],
        ['permission' => 'roles.view', 'route' => 'admin.roles.index'],
        ['permission' => 'staff.view', 'route' => 'admin.staff.index'],
        ['permission' => 'dashboard.view', 'route' => 'admin.dashboard'],
    ];

    public function __construct(protected DecisionAuthority $authority) {}

    public function scope(?User $user): AccessScope
    {
        return AccessScope::for($user);
    }

    /**
     * Deny-by-default authorization using fine-grained permission + scope.
     *
     * @param  string|array<int, string>  $permission  A permission, or any-of a set.
     * @param  array<string, mixed>  $context  e.g. vendor_id, owner_id, assigned_to, department_id, write
     */
    public function allows(?User $user, string|array $permission, array $context = []): bool
    {
        if (! $user) {
            return false;
        }

        if (! AccountStatus::isEligible($user->account_status)) {
            return false;
        }

        if (! $user->hasPortalAccess()) {
            return false;
        }

        $permissions = (array) $permission;

        $granted = false;
        foreach ($permissions as $candidate) {
            if ($user->can($candidate)) {
                $granted = true;
                break;
            }
        }

        if (! $granted) {
            return false;
        }

        return $this->withinScope($user, $context);
    }

    /**
     * Enforce authorization or throw 403 (audited).
     *
     * @param  string|array<int, string>  $permission
     * @param  array<string, mixed>  $context
     *
     * @throws AuthorizationException
     */
    public function authorize(?User $user, string|array $permission, array $context = []): void
    {
        if ($this->allows($user, $permission, $context)) {
            return;
        }

        $this->auditDenied($user, is_array($permission) ? implode('|', $permission) : $permission, $context);

        throw new AuthorizationException('This action is unauthorized.');
    }

    /**
     * Scope-only check, for record retrieval after a permission check.
     *
     * @param  array<string, mixed>  $context
     */
    public function withinScope(?User $user, array $context = []): bool
    {
        if (! $user) {
            return false;
        }

        $scope = $this->scope($user);

        if (($context['write'] ?? false) && $scope->isReadOnly()) {
            return false;
        }

        if (array_key_exists('vendor_id', $context) && ! $scope->allowsVendor($context['vendor_id'])) {
            return false;
        }

        if (array_key_exists('department_id', $context) && ! $scope->allowsDepartment($context['department_id'])) {
            return false;
        }

        if (array_key_exists('assigned_to', $context) && ! $scope->allowsTicket($context['assigned_to'], $user->id)) {
            return false;
        }

        if (array_key_exists('owner_id', $context) && ! $scope->allowsOwner($context['owner_id'], $user->id)) {
            return false;
        }

        return true;
    }

    /**
     * Ability (decision authority) evaluation: allowed / limited / requires
     * approval / prohibited.
     *
     * @return array<string, mixed>
     */
    public function ability(User $user, string $ability, ?float $amount = null): array
    {
        return $this->authority->evaluate($user, $ability, $amount);
    }

    /**
     * The route name of the best landing workspace for this staff member.
     */
    public function landingRoute(User $user): string
    {
        foreach (self::LANDING_PRIORITY as $candidate) {
            if ($user->can($candidate['permission'])) {
                return $candidate['route'];
            }
        }

        return 'admin.dashboard';
    }

    public function hasWorkspace(User $user): bool
    {
        if ($user->hasAnyRole(['super_admin', 'admin'])) {
            return true;
        }

        foreach (self::LANDING_PRIORITY as $candidate) {
            if ($user->can($candidate['permission'])) {
                return true;
            }
        }

        // Any other granular permission still grants a workspace.
        return $user->getAllPermissions()->isNotEmpty();
    }

    protected function auditDenied(?User $user, string $permission, array $context): void
    {
        try {
            AuditLogger::record('access.denied', null, [], [
                'permission' => $permission,
                'context' => $context,
                'path' => request()->path(),
            ], null, $user);
        } catch (\Throwable) {
            // Never let audit failure mask the authorization decision.
        }
    }
}
