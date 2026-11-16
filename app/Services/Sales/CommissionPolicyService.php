<?php

namespace App\Services\Sales;

use App\Models\AgentCommissionPolicy;
use Illuminate\Support\Carbon;

/**
 * Resolves which commission policy applies at a point in time and governs the
 * (versioned) lifecycle of policies. Policies are never edited in place: a
 * revision writes a new immutable row so historical commission entries always
 * remain reproducible.
 */
class CommissionPolicyService
{
    public function activePolicy(?Carbon $at = null): ?AgentCommissionPolicy
    {
        $at = $at ?: now();

        return AgentCommissionPolicy::query()
            ->where('is_active', true)
            ->where(function ($q) use ($at) {
                $q->whereNull('effective_from')->orWhere('effective_from', '<=', $at);
            })
            ->where(function ($q) use ($at) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $at);
            })
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->first()
            ?? AgentCommissionPolicy::where('is_default', true)->where('is_active', true)->first();
    }

    /**
     * Create a new policy revision. The previous revisions of the same key are
     * deactivated so only one version of a policy is ever effective.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?int $actorId = null): AgentCommissionPolicy
    {
        $key = $data['policy_key'] ?? null;

        if ($key) {
            $latest = AgentCommissionPolicy::where('policy_key', $key)->orderByDesc('version')->first();
            $data['version'] = ($latest?->version ?? 0) + 1;

            if (! empty($data['is_active'])) {
                AgentCommissionPolicy::where('policy_key', $key)->update(['is_active' => false]);
            }
        } else {
            $data['version'] = $data['version'] ?? 1;
        }

        $data['created_by'] = $data['created_by'] ?? $actorId;

        return AgentCommissionPolicy::create($data);
    }

    /**
     * Publish a revision of an existing policy.
     *
     * @param  array<string, mixed>  $data
     */
    public function revise(AgentCommissionPolicy $policy, array $data, ?int $actorId = null): AgentCommissionPolicy
    {
        $data['policy_key'] = $policy->policy_key ?: 'policy-'.$policy->id;
        $data['is_active'] = true;
        $data['created_by'] = $data['created_by'] ?? $actorId;

        return $this->create($data, $actorId);
    }

    public function deactivate(AgentCommissionPolicy $policy): void
    {
        $policy->update(['is_active' => false]);
    }
}
