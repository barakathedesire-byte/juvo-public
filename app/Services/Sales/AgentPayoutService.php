<?php

namespace App\Services\Sales;

use App\Models\AgentCommissionEntry;
use App\Models\AgentPayoutItem;
use App\Models\AgentPayoutRequest;
use App\Models\BusinessRule;
use App\Models\SalesRep;
use App\Services\Admin\AuditLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Concurrency-safe agent payouts.
 *
 * A payout reserves specific commission entries (state = payable) inside a
 * locking transaction, so the same balance can never be claimed twice by two
 * simultaneous requests. Only collected funds are ever payable — the engine
 * keeps COD orders out of the eligible pool until cash is received.
 */
class AgentPayoutService
{
    public function __construct(
        protected AgentCommissionEngine $engine,
        protected AuditLogger $audit,
    ) {}

    public function balance(SalesRep $agent): array
    {
        return $this->engine->balanceFor($agent);
    }

    /**
     * @param  array<string, mixed>  $meta  method / account overrides
     */
    public function request(SalesRep $agent, ?float $amount = null, array $meta = []): AgentPayoutRequest
    {
        if (! $agent->canOperate()) {
            throw new RuntimeException('This agent is not active and verified and cannot request a payout.');
        }

        if ($agent->payout_status !== 'verified') {
            throw new RuntimeException('A verified payout account is required before requesting a payout.');
        }

        if (AgentPayoutRequest::where('agent_id', $agent->id)->open()->exists()) {
            throw new RuntimeException('There is already an open payout request for this agent.');
        }

        $min = (float) BusinessRule::number('agent_min_payout', 0);

        return DB::transaction(function () use ($agent, $amount, $min, $meta) {
            $entries = AgentCommissionEntry::query()
                ->where('agent_id', $agent->id)
                ->whereIn('state', ['eligible', 'approved'])
                ->where('amount', '>', 0)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $available = round((float) $entries->sum('amount'), 2);

            if ($available <= 0) {
                throw new RuntimeException('There is no available commission balance to pay out.');
            }

            $requested = $amount !== null ? round($amount, 2) : $available;

            if ($requested > $available + 0.001) {
                throw new RuntimeException('Requested amount exceeds the available balance.');
            }

            if ($min > 0 && $requested < $min) {
                throw new RuntimeException('The minimum payout amount is '.number_format($min, 2).'.');
            }

            $picked = [];
            $sum = 0.0;

            foreach ($entries as $entry) {
                if ($sum >= $requested) {
                    break;
                }
                $picked[] = $entry;
                $sum = round($sum + (float) $entry->amount, 2);
            }

            if (empty($picked)) {
                throw new RuntimeException('There is no available commission balance to pay out.');
            }

            $payout = AgentPayoutRequest::create([
                'reference' => $this->reference(),
                'agent_id' => $agent->id,
                'amount' => $sum,
                'currency' => $agent->currency ?: 'TZS',
                'method' => $meta['method'] ?? $agent->payout_method,
                'account_name' => $meta['account_name'] ?? $agent->payout_account_name,
                'account_number' => $meta['account_number'] ?? $agent->payout_account_number,
                'status' => 'requested',
                'requested_at' => now(),
                'notes' => $meta['notes'] ?? null,
            ]);

            foreach ($picked as $entry) {
                $entry->update(['state' => 'payable', 'payout_request_id' => $payout->id]);

                AgentPayoutItem::create([
                    'agent_payout_request_id' => $payout->id,
                    'agent_commission_entry_id' => $entry->id,
                    'amount' => $entry->amount,
                ]);
            }

            $this->audit->log('agent.payout.requested', $payout, [], [
                'agent_id' => $agent->id,
                'amount' => $sum,
            ]);

            return $payout;
        });
    }

    public function approve(AgentPayoutRequest $payout, int $actorId, ?string $reason = null): AgentPayoutRequest
    {
        return $this->decide($payout, 'approved', $actorId, $reason);
    }

    public function reject(AgentPayoutRequest $payout, int $actorId, string $reason): AgentPayoutRequest
    {
        return DB::transaction(function () use ($payout, $actorId, $reason) {
            $payout = $this->lockOpen($payout);

            $this->releaseEntries($payout);

            $payout->update([
                'status' => 'rejected',
                'rejected_at' => now(),
                'rejection_reason' => $reason,
                'approved_by' => $actorId,
            ]);

            $this->audit->log('agent.payout.rejected', $payout, [], ['status' => 'rejected'], $reason, \App\Models\User::find($actorId));

            return $payout;
        });
    }

    public function markProcessing(AgentPayoutRequest $payout, int $actorId): AgentPayoutRequest
    {
        return $this->decide($payout, 'processing', $actorId);
    }

    public function markPaid(AgentPayoutRequest $payout, int $actorId, ?string $providerReference = null): AgentPayoutRequest
    {
        return DB::transaction(function () use ($payout, $actorId, $providerReference) {
            $payout = $this->lockOpen($payout);

            AgentCommissionEntry::where('payout_request_id', $payout->id)
                ->where('state', 'payable')
                ->update(['state' => 'paid', 'paid_at' => now()]);

            $payout->update([
                'status' => 'paid',
                'paid_at' => now(),
                'processed_by' => $actorId,
                'provider_reference' => $providerReference,
            ]);

            $this->audit->log('agent.payout.paid', $payout, [], [
                'status' => 'paid',
                'provider_reference' => $providerReference,
            ], null, \App\Models\User::find($actorId));

            return $payout;
        });
    }

    public function markFailed(AgentPayoutRequest $payout, int $actorId, string $reason): AgentPayoutRequest
    {
        return DB::transaction(function () use ($payout, $actorId, $reason) {
            $payout = $this->lockOpen($payout);

            $this->releaseEntries($payout);

            $payout->update([
                'status' => 'failed',
                'processed_by' => $actorId,
                'rejection_reason' => $reason,
            ]);

            $this->audit->log('agent.payout.failed', $payout, [], ['status' => 'failed'], $reason, \App\Models\User::find($actorId));

            return $payout;
        });
    }

    protected function decide(AgentPayoutRequest $payout, string $status, int $actorId, ?string $reason = null): AgentPayoutRequest
    {
        return DB::transaction(function () use ($payout, $status, $actorId, $reason) {
            $payout = $this->lockOpen($payout);

            $payout->update([
                'status' => $status,
                'approved_at' => $status === 'approved' ? now() : $payout->approved_at,
                'processing_at' => $status === 'processing' ? now() : $payout->processing_at,
                'approved_by' => $actorId,
            ]);

            $this->audit->log('agent.payout.'.$status, $payout, [], ['status' => $status], $reason, \App\Models\User::find($actorId));

            return $payout;
        });
    }

    protected function releaseEntries(AgentPayoutRequest $payout): void
    {
        AgentCommissionEntry::where('payout_request_id', $payout->id)
            ->where('state', 'payable')
            ->update(['state' => 'eligible', 'payout_request_id' => null]);
    }

    protected function lockOpen(AgentPayoutRequest $payout): AgentPayoutRequest
    {
        $fresh = AgentPayoutRequest::whereKey($payout->getKey())->lockForUpdate()->first();

        if (! $fresh || ! $fresh->isOpen()) {
            throw new RuntimeException('This payout request is no longer open.');
        }

        return $fresh;
    }

    protected function reference(): string
    {
        return 'APY-'.strtoupper(bin2hex(random_bytes(5)));
    }
}
