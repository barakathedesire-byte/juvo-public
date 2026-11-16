<?php

namespace App\Services\Admin;

use App\Models\RoleDecisionPolicy;
use App\Models\User;
use App\Support\Access\AbilityCatalog;

/**
 * Evaluates a governed ability for a user, combining:
 *  - the granular permission (can the user act at all), and
 *  - the role decision-authority policy (allowed / limited / requires
 *    approval / prohibited) with numeric thresholds.
 *
 * Thresholds are stored in backend configuration, never hardcoded in the UI.
 */
class DecisionAuthority
{
    public const ALLOWED = 'allowed';

    public const REQUIRES_APPROVAL = 'requires_approval';

    public const PROHIBITED = 'prohibited';

    /**
     * @return array{ability:string,mode:string,allowed:bool,requires_approval:bool,can_finalize:bool,limit:?float,has_permission:bool}
     */
    public function evaluate(User $user, string $ability, ?float $amount = null): array
    {
        $definition = AbilityCatalog::get($ability);
        $limitKey = $definition['limit_key'] ?? null;
        $permission = $definition['permission'] ?? null;

        $hasPermission = $permission === null || $user->can($permission);

        if (! $hasPermission) {
            return $this->result($ability, self::PROHIBITED, false, false, null, false);
        }

        $roleIds = $user->roles()->pluck('id')->all();

        $policies = RoleDecisionPolicy::whereIn('role_id', $roleIds)
            ->where('ability', $ability)
            ->get();

        if ($policies->isEmpty()) {
            // No explicit authority configured: permission alone decides.
            return $this->result($ability, self::ALLOWED, true, false, null, true);
        }

        $best = self::PROHIBITED;
        $limit = null;

        foreach ($policies as $policy) {
            $threshold = $limitKey ? RoleDecisionPolicy::thresholdFor($policy, $limitKey) : null;
            $limit = $limit ?? $threshold;

            $effective = $this->effectiveMode($policy->mode, $threshold, $amount);

            if ($effective === self::ALLOWED) {
                $best = self::ALLOWED;
                break;
            }

            if ($effective === self::REQUIRES_APPROVAL && $best === self::PROHIBITED) {
                $best = self::REQUIRES_APPROVAL;
            }
        }

        return match ($best) {
            self::ALLOWED => $this->result($ability, self::ALLOWED, true, false, $limit, true),
            self::REQUIRES_APPROVAL => $this->result($ability, self::REQUIRES_APPROVAL, true, true, $limit, true),
            default => $this->result($ability, self::PROHIBITED, false, false, $limit, true),
        };
    }

    protected function effectiveMode(string $mode, ?float $threshold, ?float $amount): string
    {
        return match ($mode) {
            RoleDecisionPolicy::MODE_ALLOWED => self::ALLOWED,
            RoleDecisionPolicy::MODE_REQUIRES_APPROVAL => self::REQUIRES_APPROVAL,
            RoleDecisionPolicy::MODE_LIMITED => ($threshold !== null && $amount !== null && $amount > $threshold)
                ? self::REQUIRES_APPROVAL
                : self::ALLOWED,
            default => self::PROHIBITED,
        };
    }

    private function result(string $ability, string $mode, bool $allowed, bool $requiresApproval, ?float $limit, bool $hasPermission): array
    {
        return [
            'ability' => $ability,
            'mode' => $mode,
            'allowed' => $allowed,
            'requires_approval' => $requiresApproval,
            'can_finalize' => $allowed && ! $requiresApproval,
            'limit' => $limit,
            'has_permission' => $hasPermission,
        ];
    }
}
