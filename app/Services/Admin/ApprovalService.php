<?php

namespace App\Services\Admin;

use App\Models\ApprovalPolicy;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRequestStep;
use App\Models\User;
use App\Support\Access\AbilityCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Shared maker-checker engine. One engine for Finance, Support, Sales, Staff
 * and Vendor Operations. Every decision revalidates permission, separation of
 * duties and record freshness inside a locking transaction.
 */
class ApprovalService
{
    public function __construct(
        protected DecisionAuthority $authority,
        protected AuditLogger $audit,
    ) {}

    /**
     * Create a pending approval request for a governed ability.
     */
    public function submit(
        User $requester,
        string $ability,
        ?Model $subject = null,
        array $payload = [],
        ?string $reason = null,
        ?float $amount = null,
    ): ApprovalRequest {
        $policy = ApprovalPolicy::forAbility($ability);
        $definition = AbilityCatalog::get($ability);

        if (! $policy && ! $definition) {
            throw new RuntimeException("Unknown governed ability [{$ability}].");
        }

        $risk = $policy->risk ?? $definition['risk'] ?? 'medium';
        $stages = max(1, (int) ($policy->stages ?? 1));

        return DB::transaction(function () use ($requester, $ability, $subject, $payload, $reason, $amount, $policy, $risk, $stages) {
            $request = ApprovalRequest::create([
                'reference' => $this->reference(),
                'policy_key' => $policy?->key ?? $ability,
                'ability' => $ability,
                'module' => $policy->module ?? $definition['module'] ?? 'general',
                'approvable_type' => $subject?->getMorphClass(),
                'approvable_id' => $subject?->getKey(),
                'requester_id' => $requester->id,
                'requester_role' => $requester->roles()->value('name'),
                'reason' => $reason,
                'payload' => $payload,
                'amount' => $amount,
                'risk' => $risk,
                'required_approvals' => $stages,
                'received_approvals' => 0,
                'status' => 'pending',
            ]);

            $this->step($request, $requester, 'submitted', $reason);

            $this->audit->log('approval.submitted', $request, [], [
                'ability' => $ability,
                'amount' => $amount,
            ], $reason, $requester);

            return $request;
        });
    }

    /**
     * Approve an open request. Self-approval is prohibited whenever the request
     * is maker-checker governed.
     */
    public function approve(User $actor, ApprovalRequest $request, ?string $reason = null): ApprovalRequest
    {
        return DB::transaction(function () use ($actor, $request, $reason) {
            $request = $this->lockOpen($request);

            if ($actor->id === $request->requester_id) {
                throw new RuntimeException('Separation of duties: you cannot approve your own request.');
            }

            if (! $this->mayApprove($actor, $request)) {
                throw new RuntimeException('You are not authorized to approve this request.');
            }

            $request->received_approvals = $request->received_approvals + 1;

            if ($request->received_approvals >= $request->required_approvals) {
                $request->status = 'approved';
                $request->decided_by = $actor->id;
                $request->decided_at = now();
                $request->decision_reason = $reason;
            }

            $request->save();

            $this->step($request, $actor, 'approved', $reason);
            $this->audit->log('approval.approved', $request, [], [
                'received' => $request->received_approvals,
                'required' => $request->required_approvals,
            ], $reason, $actor);

            return $request;
        });
    }

    public function reject(User $actor, ApprovalRequest $request, ?string $reason = null): ApprovalRequest
    {
        return DB::transaction(function () use ($actor, $request, $reason) {
            $request = $this->lockOpen($request);

            if ($actor->id === $request->requester_id) {
                throw new RuntimeException('You cannot decide on your own request.');
            }

            if (! $this->mayApprove($actor, $request)) {
                throw new RuntimeException('You are not authorized to reject this request.');
            }

            $request->status = 'rejected';
            $request->decided_by = $actor->id;
            $request->decided_at = now();
            $request->decision_reason = $reason;
            $request->save();

            $this->step($request, $actor, 'rejected', $reason);
            $this->audit->log('approval.rejected', $request, [], [], $reason, $actor);

            return $request;
        });
    }

    public function requestChanges(User $actor, ApprovalRequest $request, string $reason): ApprovalRequest
    {
        return DB::transaction(function () use ($actor, $request, $reason) {
            $request = $this->lockOpen($request);

            if ($actor->id === $request->requester_id) {
                throw new RuntimeException('Separation of duties: you cannot review your own request.');
            }

            if (! $this->mayApprove($actor, $request)) {
                throw new RuntimeException('You are not authorized to review this request.');
            }

            $request->status = 'changes_requested';
            $request->decided_by = $actor->id;
            $request->decided_at = now();
            $request->decision_reason = $reason;
            $request->save();

            $this->step($request, $actor, 'changes_requested', $reason);
            $this->audit->log('approval.changes_requested', $request, [], [], $reason, $actor);

            return $request;
        });
    }

    public function reassign(User $actor, ApprovalRequest $request, User $assignee): ApprovalRequest
    {
        return DB::transaction(function () use ($actor, $request, $assignee) {
            $request = $this->lockOpen($request);

            if (! $this->mayApprove($actor, $request)) {
                throw new RuntimeException('You are not authorized to reassign this request.');
            }

            $request->assigned_to = $assignee->id;
            $request->save();

            $this->step($request, $actor, 'reassigned', null, ['assigned_to' => $assignee->id]);

            return $request;
        });
    }

    public function escalate(User $actor, ApprovalRequest $request, ?string $reason = null): ApprovalRequest
    {
        return DB::transaction(function () use ($actor, $request, $reason) {
            $request = $this->lockOpen($request);

            $request->status = 'escalated';
            $request->save();

            $this->step($request, $actor, 'escalated', $reason);
            $this->audit->log('approval.escalated', $request, [], [], $reason, $actor);

            return $request;
        });
    }

    /**
     * Mark an approved request as executed after the domain action ran.
     */
    public function markExecuted(ApprovalRequest $request, ?User $actor = null): ApprovalRequest
    {
        if ($request->status !== 'approved') {
            throw new RuntimeException('Only approved requests can be executed.');
        }

        $request->status = 'executed';
        $request->executed_at = now();
        $request->save();

        $this->step($request, $actor, 'executed', null);

        return $request;
    }

    /**
     * True when the ability requires a maker-checker approval under policy.
     */
    public function requiresApproval(string $ability, ?float $amount = null): bool
    {
        $policy = ApprovalPolicy::forAbility($ability);

        if (! $policy || ! $policy->requires_approval) {
            return false;
        }

        if ($amount !== null && $policy->min_amount !== null && (float) $policy->min_amount > $amount) {
            return false;
        }

        if ($amount !== null && $policy->max_amount !== null && (float) $policy->max_amount < $amount) {
            return false;
        }

        return true;
    }

    /**
     * An open (pending/escalated) request for the same ability and record.
     */
    public function pendingFor(string $ability, Model $subject, ?float $amount = null): ?ApprovalRequest
    {
        return ApprovalRequest::query()
            ->where('ability', $ability)
            ->where('approvable_type', $subject->getMorphClass())
            ->where('approvable_id', $subject->getKey())
            ->whereIn('status', ApprovalRequest::OPEN_STATUSES)
            ->when($amount !== null, fn ($q) => $q->where('amount', $amount))
            ->latest('id')
            ->first();
    }

    /**
     * An approved-but-not-yet-executed request for the same ability and record.
     */
    public function approvedFor(string $ability, Model $subject, ?float $amount = null): ?ApprovalRequest
    {
        return ApprovalRequest::query()
            ->where('ability', $ability)
            ->where('approvable_type', $subject->getMorphClass())
            ->where('approvable_id', $subject->getKey())
            ->where('status', 'approved')
            ->when($amount !== null, fn ($q) => $q->where('amount', $amount))
            ->latest('id')
            ->first();
    }

    protected function mayApprove(User $actor, ApprovalRequest $request): bool
    {
        if ($actor->hasRole('super_admin') || $actor->hasRole('admin')) {
            return true;
        }

        $policy = $request->policy_key ? ApprovalPolicy::where('key', $request->policy_key)->first() : null;
        $permission = $policy->approver_permission
            ?? AbilityCatalog::get($request->ability)['approver_permission']
            ?? 'approvals.approve';

        return $actor->can($permission);
    }

    protected function lockOpen(ApprovalRequest $request): ApprovalRequest
    {
        $fresh = ApprovalRequest::whereKey($request->getKey())->lockForUpdate()->first();

        if (! $fresh || ! $fresh->isOpen()) {
            throw new RuntimeException('This request is no longer open for a decision.');
        }

        return $fresh;
    }

    protected function step(ApprovalRequest $request, ?User $actor, string $action, ?string $reason = null, array $meta = []): void
    {
        ApprovalRequestStep::create([
            'approval_request_id' => $request->id,
            'actor_id' => $actor?->id,
            'action' => $action,
            'reason' => $reason,
            'meta' => $meta ?: null,
            'created_at' => now(),
        ]);
    }

    protected function reference(): string
    {
        return 'APR-'.strtoupper(bin2hex(random_bytes(5)));
    }
}
