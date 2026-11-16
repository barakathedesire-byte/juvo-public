<?php

namespace App\Services\Sales;

use App\Models\AgentStatusHistory;
use App\Models\SalesRep;
use App\Services\Admin\AuditLogger;
use Illuminate\Support\Str;

/**
 * Agent onboarding. Anyone can apply; the application is created in the
 * non-operational "applied" state and must pass verification and approval
 * before the agent can transact (see AgentApprovalService).
 */
class AgentRegistrationService
{
    public function __construct(
        protected AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function apply(array $data, ?int $actorId = null): SalesRep
    {
        $code = $data['code'] ?? $this->uniqueCode($data['name'] ?? 'AGENT');

        $agent = SalesRep::create([
            'user_id' => $data['user_id'] ?? null,
            'code' => $code,
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'region_id' => $data['region_id'] ?? null,
            'location_id' => $data['location_id'] ?? null,
            'sponsor_id' => $data['sponsor_id'] ?? null,
            'commission_rate' => $data['commission_rate'] ?? 0,
            'monthly_target' => $data['monthly_target'] ?? 0,
            'status' => 'applied',
            'verification_status' => 'unverified',
            'is_active' => false,
            'terms_version' => $data['terms_version'] ?? null,
            'terms_accepted_at' => ! empty($data['terms_accepted']) ? now() : null,
            'applied_at' => now(),
            'currency' => $data['currency'] ?? 'TZS',
            'notes' => $data['notes'] ?? null,
        ]);

        $this->recordHistory($agent, null, 'applied', $actorId, 'Application submitted');
        $this->audit->log('agent.applied', $agent, [], $agent->only(['code', 'name', 'status']));

        return $agent;
    }

    public function uniqueCode(string $name): string
    {
        $base = Str::upper(Str::slug($name) ?: 'AGENT');
        $base = Str::limit($base, 40, '');
        $code = $base;

        while (SalesRep::where('code', $code)->exists()) {
            $code = $base.'-'.Str::upper(Str::random(4));
        }

        return $code;
    }

    public function recordHistory(SalesRep $agent, ?string $from, string $to, ?int $actorId, ?string $reason = null, array $meta = []): AgentStatusHistory
    {
        return AgentStatusHistory::create([
            'agent_id' => $agent->id,
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $actorId,
            'reason' => $reason,
            'meta' => $meta ?: null,
        ]);
    }
}
