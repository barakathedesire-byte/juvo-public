<?php

namespace App\Services\Sales;

use App\Models\SalesRep;
use App\Services\Admin\AuditLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Agent lifecycle state machine with separation of duties: the person who
 * applied (the linked user) can never verify, approve or reinstate their own
 * application.
 */
class AgentApprovalService
{
    public function __construct(
        protected AgentRegistrationService $registration,
        protected AuditLogger $audit,
    ) {}

    /**
     * Allowed transitions, keyed by target status.
     */
    protected const TRANSITIONS = [
        'applied' => ['draft', 'verification', 'rejected'],
        'verification' => ['review', 'rejected'],
        'review' => ['approved', 'rejected', 'verification'],
        'approved' => ['active', 'suspended', 'rejected'],
        'active' => ['suspended', 'closed'],
        'suspended' => ['active', 'closed', 'rejected'],
        'rejected' => ['verification', 'review'],
        'closed' => [],
        'draft' => ['applied'],
    ];

    public function submitForVerification(SalesRep $agent, int $actorId, ?string $reason = null): SalesRep
    {
        return $this->transition($agent, 'verification', $actorId, $reason);
    }

    public function startReview(SalesRep $agent, int $actorId, ?string $reason = null): SalesRep
    {
        return $this->transition($agent, 'review', $actorId, $reason);
    }

    /**
     * Mark verification complete. This is a KYC/business-verification fact,
     * separate from the approval decision.
     */
    public function verify(SalesRep $agent, int $actorId, bool $passed, ?string $notes = null): SalesRep
    {
        $before = ['verification_status' => $agent->verification_status];

        $agent->update([
            'verification_status' => $passed ? 'verified' : 'failed',
            'payout_status' => $passed && $agent->payout_status === 'incomplete' ? 'pending' : $agent->payout_status,
        ]);

        $this->audit->log('agent.verification', $agent, $before, [
            'verification_status' => $agent->verification_status,
        ], $notes, \App\Models\User::find($actorId));

        return $agent;
    }

    public function approve(SalesRep $agent, int $actorId, ?string $reason = null): SalesRep
    {
        $this->guardSeparation($agent, $actorId);

        if ($agent->verification_status !== 'verified') {
            throw new RuntimeException('An agent must be verified before approval.');
        }

        return DB::transaction(function () use ($agent, $actorId, $reason) {
            $agent->update([
                'status' => 'active',
                'is_active' => true,
                'approved_at' => now(),
                'approved_by' => $actorId,
                'rejected_at' => null,
                'rejection_reason' => null,
            ]);

            $this->registration->recordHistory($agent, 'review', 'active', $actorId, $reason ?? 'Approved');
            $this->audit->log('agent.approved', $agent, [], ['status' => 'active'], $reason, \App\Models\User::find($actorId));

            return $agent;
        });
    }

    public function reject(SalesRep $agent, int $actorId, string $reason): SalesRep
    {
        $this->guardSeparation($agent, $actorId);

        return DB::transaction(function () use ($agent, $actorId, $reason) {
            $from = $agent->status;

            $agent->update([
                'status' => 'rejected',
                'is_active' => false,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);

            $this->registration->recordHistory($agent, $from, 'rejected', $actorId, $reason);
            $this->audit->log('agent.rejected', $agent, ['status' => $from], ['status' => 'rejected'], $reason, \App\Models\User::find($actorId));

            return $agent;
        });
    }

    public function suspend(SalesRep $agent, int $actorId, string $reason): SalesRep
    {
        return DB::transaction(function () use ($agent, $actorId, $reason) {
            $from = $agent->status;

            $agent->update([
                'status' => 'suspended',
                'is_active' => false,
                'suspended_at' => now(),
                'suspension_reason' => $reason,
            ]);

            $this->registration->recordHistory($agent, $from, 'suspended', $actorId, $reason);
            $this->audit->log('agent.suspended', $agent, ['status' => $from], ['status' => 'suspended'], $reason, \App\Models\User::find($actorId));

            return $agent;
        });
    }

    public function reinstate(SalesRep $agent, int $actorId, ?string $reason = null): SalesRep
    {
        $this->guardSeparation($agent, $actorId);

        if (! $agent->isApproved() && $agent->status !== 'suspended') {
            throw new RuntimeException('Only suspended or approved agents can be reinstated.');
        }

        return DB::transaction(function () use ($agent, $actorId, $reason) {
            $from = $agent->status;

            $agent->update([
                'status' => 'active',
                'is_active' => true,
                'suspended_at' => null,
                'suspension_reason' => null,
            ]);

            $this->registration->recordHistory($agent, $from, 'active', $actorId, $reason ?? 'Reinstated');
            $this->audit->log('agent.reinstated', $agent, ['status' => $from], ['status' => 'active'], $reason, \App\Models\User::find($actorId));

            return $agent;
        });
    }

    public function close(SalesRep $agent, int $actorId, ?string $reason = null): SalesRep
    {
        return $this->transition($agent, 'closed', $actorId, $reason);
    }

    public function transition(SalesRep $agent, string $to, int $actorId, ?string $reason = null): SalesRep
    {
        $from = $agent->status;
        $allowed = self::TRANSITIONS[$from] ?? [];

        if (! in_array($to, $allowed, true)) {
            throw new RuntimeException("Cannot move agent #{$agent->id} from '{$from}' to '{$to}'.");
        }

        return DB::transaction(function () use ($agent, $from, $to, $actorId, $reason) {
            $agent->update([
                'status' => $to,
                'is_active' => in_array($to, ['approved', 'active'], true) ? true : false,
            ]);

            $this->registration->recordHistory($agent, $from, $to, $actorId, $reason);
            $this->audit->log('agent.status', $agent, ['status' => $from], ['status' => $to], $reason, \App\Models\User::find($actorId));

            return $agent;
        });
    }

    protected function guardSeparation(SalesRep $agent, int $actorId): void
    {
        if ($agent->user_id !== null && $agent->user_id === $actorId) {
            throw new RuntimeException('Separation of duties: you cannot decide on your own agent application.');
        }
    }
}
