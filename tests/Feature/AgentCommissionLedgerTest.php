<?php

namespace Tests\Feature;

use App\Models\AgentCommissionEntry;
use App\Services\Sales\AgentCommissionEngine;
use App\Services\Sales\AgentPayoutService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use RuntimeException;
use Tests\Concerns\BuildsAgentNetwork;
use Tests\TestCase;

class AgentCommissionLedgerTest extends TestCase
{
    use BuildsAgentNetwork;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetAgentPolicies();
    }

    protected function engine()
    {
        return app(AgentCommissionEngine::class);
    }

    protected function eligibleEntry(): AgentCommissionEntry
    {
        $this->agentPolicy();
        $agent = $this->agent();
        $order = $this->agentOrder('delivered', 'paid');
        $this->attributeAgent($order, $agent);

        $this->engine()->markEligibleForOrder($order);

        return AgentCommissionEntry::where('agent_id', $agent->id)->where('role', 'direct')->firstOrFail();
    }

    public function test_reversing_a_paid_entry_creates_a_negative_clawback_adjustment(): void
    {
        $entry = $this->eligibleEntry();
        $payout = app(AgentPayoutService::class)->request($entry->agent);
        app(AgentPayoutService::class)->markPaid($payout, 1);

        $this->assertSame('paid', $entry->fresh()->state);

        $adjustment = $this->engine()->reverseEntry($entry->fresh(), 'chargeback');

        $this->assertSame('adjustment', $adjustment->role);
        $this->assertSame('eligible', $adjustment->state);
        $this->assertSame(-5000.0, (float) $adjustment->amount);

        $balance = $this->engine()->balanceFor($entry->agent);
        $this->assertSame(5000.0, $balance['paid']);
        $this->assertSame(-5000.0, $balance['available']);
    }

    public function test_a_commission_reserved_in_an_open_payout_cannot_be_reversed(): void
    {
        $entry = $this->eligibleEntry();
        app(AgentPayoutService::class)->request($entry->agent);

        $this->assertSame('payable', $entry->fresh()->state);

        $this->expectException(RuntimeException::class);

        $this->engine()->reverseEntry($entry->fresh(), 'too late');
    }

    public function test_reversal_is_idempotent(): void
    {
        $entry = $this->eligibleEntry();

        $first = $this->engine()->reverseEntry($entry->fresh(), 'duplicate');
        $again = $this->engine()->reverseEntry($entry->fresh(), 'duplicate');

        $this->assertNotNull($first);
        $this->assertNull($again);
        $this->assertSame('reversed', $entry->fresh()->state);
        $this->assertSame(1, AgentCommissionEntry::where('reversal_of_id', $entry->id)->count());
    }

    public function test_reverse_for_order_does_not_touch_already_reversed_rows(): void
    {
        $entry = $this->eligibleEntry();
        $order = $entry->order;

        $this->assertSame(1, $this->engine()->reverseForOrder($order, 'canceled'));
        $this->assertSame(0, $this->engine()->reverseForOrder($order, 'canceled'));
    }

    public function test_commissions_are_not_eligible_until_the_order_is_paid(): void
    {
        $this->agentPolicy();
        $agent = $this->agent();
        $order = $this->agentOrder('delivered', 'pending', 100000);
        $this->attributeAgent($order, $agent);

        $entry = AgentCommissionEntry::where('agent_id', $agent->id)->firstOrFail();
        $this->assertSame('pending', $entry->state);

        $this->assertSame(0, $this->engine()->markEligibleForOrder($order->fresh()));

        $order->update(['payment_status' => 'paid']);

        $this->assertSame(1, $this->engine()->markEligibleForOrder($order->fresh()));
        $this->assertSame('eligible', $entry->fresh()->state);
    }
}
