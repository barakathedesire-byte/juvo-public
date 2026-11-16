<?php

namespace Tests\Feature;

use App\Models\SupportCannedResponse;
use App\Models\SupportSlaPolicy;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Services\Support\SupportSlaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class SupportManagementTest extends TestCase
{
    use DatabaseTransactions;
    use GrantsAdminAccess;

    protected function staffWithPermissions(string $roleName, array $permissions): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $user = User::factory()->create(['role' => $roleName]);
        $user->assignRole($role);
        $user->givePermissionTo($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    protected function ticket(array $attrs = []): SupportTicket
    {
        return SupportTicket::create(array_merge([
            'ticket_number' => 'TKT-TEST-'.uniqid(),
            'subject' => 'Help with order',
            'category' => 'order',
            'priority' => 'normal',
            'status' => 'open',
        ], $attrs));
    }

    public function test_new_ticket_receives_sla_deadlines_from_priority_policy(): void
    {
        $policy = SupportSlaPolicy::where('priority', 'urgent')->first();

        $ticket = $this->ticket(['priority' => 'urgent']);

        $this->assertNotNull($ticket->first_response_due_at);
        $this->assertNotNull($ticket->resolution_due_at);
        $this->assertEqualsWithDelta(
            $policy->first_response_minutes,
            now()->diffInMinutes($ticket->first_response_due_at),
            1
        );
        $this->assertEqualsWithDelta(
            $policy->resolution_minutes,
            now()->diffInMinutes($ticket->resolution_due_at),
            1
        );
    }

    public function test_overdue_ticket_is_reported_as_sla_breached(): void
    {
        $ticket = $this->ticket();
        $ticket->update([
            'first_response_due_at' => now()->subHours(5),
            'resolution_due_at' => now()->subHours(2),
        ]);

        $sla = app(SupportSlaService::class);

        $this->assertTrue($sla->isBreached($ticket->fresh()));
        $this->assertSame('breached', $sla->state($ticket->fresh()));
    }

    public function test_first_response_marks_sla_met(): void
    {
        $ticket = $this->ticket();
        $ticket->update(['first_response_due_at' => now()->subHour()]);

        $sla = app(SupportSlaService::class);
        $sla->markFirstResponse($ticket);

        $this->assertNotNull($ticket->fresh()->first_responded_at);
        $this->assertSame('on_track', $sla->state($ticket->fresh()));
    }

    public function test_staff_with_support_view_can_open_the_inbox(): void
    {
        $agent = $this->staffWithPermissions('support_reader_'.uniqid(), ['support.view']);

        $this->actingAs($agent)
            ->get(route('admin.support-tickets.index'))
            ->assertOk();
    }

    public function test_staff_without_support_permission_cannot_open_the_inbox(): void
    {
        $user = $this->staffWithPermissions('no_support_'.uniqid(), ['orders.view']);

        $this->actingAs($user)
            ->get(route('admin.support-tickets.index'))
            ->assertForbidden();
    }

    public function test_agent_can_assign_a_ticket_and_it_is_audited(): void
    {
        $agent = $this->staffWithPermissions('assigner_'.uniqid(), ['support.view', 'support.assign']);
        $assignee = $this->staffWithPermissions('agent_target_'.uniqid(), ['support.view', 'support.reply']);
        $ticket = $this->ticket();

        $this->actingAs($agent)
            ->post(route('admin.support-tickets.assign', $ticket->id), ['assigned_to' => $assignee->id])
            ->assertRedirect();

        $this->assertSame($assignee->id, $ticket->fresh()->assigned_to);
        $this->assertDatabaseHas('audit_logs', ['action' => 'support.ticket.assigned']);
    }

    public function test_staff_without_assign_permission_cannot_assign(): void
    {
        $agent = $this->staffWithPermissions('viewer_only_'.uniqid(), ['support.view']);
        $ticket = $this->ticket();

        $this->actingAs($agent)
            ->post(route('admin.support-tickets.assign', $ticket->id), ['assigned_to' => $agent->id])
            ->assertForbidden();
    }

    public function test_escalation_raises_priority_and_logs_an_internal_note(): void
    {
        $agent = $this->staffWithPermissions('escalator_'.uniqid(), ['support.view', 'support.escalate']);
        $ticket = $this->ticket(['priority' => 'low']);

        $this->actingAs($agent)
            ->post(route('admin.support-tickets.escalate', $ticket->id), ['reason' => 'Customer is furious'])
            ->assertRedirect();

        $fresh = $ticket->fresh();
        $this->assertSame('normal', $fresh->priority);
        $this->assertSame(1, $fresh->escalation_level);
        $this->assertNotNull($fresh->escalated_at);
        $this->assertDatabaseHas('support_ticket_messages', [
            'support_ticket_id' => $ticket->id,
            'is_internal' => true,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'support.ticket.escalated']);
    }

    public function test_staff_without_escalate_permission_cannot_escalate(): void
    {
        $agent = $this->staffWithPermissions('no_escalate_'.uniqid(), ['support.view']);
        $ticket = $this->ticket();

        $this->actingAs($agent)
            ->post(route('admin.support-tickets.escalate', $ticket->id))
            ->assertForbidden();
    }

    public function test_closing_requires_close_permission(): void
    {
        $agent = $this->staffWithPermissions('no_close_'.uniqid(), ['support.view', 'support.reply']);
        $ticket = $this->ticket();

        $this->actingAs($agent)
            ->post(route('admin.support-tickets.status', $ticket->id), ['status' => 'closed', 'priority' => 'normal'])
            ->assertForbidden();

        $this->assertSame('open', $ticket->fresh()->status);
    }

    public function test_resolving_a_ticket_records_resolution_time(): void
    {
        $agent = $this->staffWithPermissions('closer_'.uniqid(), ['support.view', 'support.close']);
        $ticket = $this->ticket();

        $this->actingAs($agent)
            ->post(route('admin.support-tickets.status', $ticket->id), ['status' => 'resolved', 'priority' => 'normal'])
            ->assertRedirect();

        $fresh = $ticket->fresh();
        $this->assertSame('resolved', $fresh->status);
        $this->assertNotNull($fresh->resolved_at);
    }

    public function test_reopening_requires_reopen_permission_and_counts(): void
    {
        $closer = $this->staffWithPermissions('reopener_'.uniqid(), ['support.view', 'support.close', 'support.reopen']);
        $ticket = $this->ticket(['status' => 'open']);

        $this->actingAs($closer)
            ->post(route('admin.support-tickets.status', $ticket->id), ['status' => 'resolved', 'priority' => 'normal']);
        $this->actingAs($closer)
            ->post(route('admin.support-tickets.status', $ticket->id), ['status' => 'open', 'priority' => 'normal'])
            ->assertRedirect();

        $fresh = $ticket->fresh();
        $this->assertSame('open', $fresh->status);
        $this->assertSame(1, $fresh->reopened_count);
        $this->assertNull($fresh->resolved_at);
    }

    public function test_canned_response_crud_is_gated_by_manage_canned(): void
    {
        $manager = $this->staffWithPermissions('canned_manager_'.uniqid(), ['support.manage_canned']);
        $viewer = $this->staffWithPermissions('canned_viewer_'.uniqid(), ['support.view']);

        $this->actingAs($manager)
            ->post(route('admin.support.canned.store'), [
                'title' => 'Greeting',
                'body' => 'Hello and thanks for reaching out.',
                'category' => 'general',
            ])
            ->assertRedirect();

        $response = SupportCannedResponse::where('title', 'Greeting')->first();
        $this->assertNotNull($response);

        $this->actingAs($viewer)
            ->get(route('admin.support.canned.index'))
            ->assertForbidden();

        $this->actingAs($manager)
            ->put(route('admin.support.canned.update', $response->id), [
                'title' => 'Greeting v2',
                'body' => 'Updated body',
                'category' => 'general',
                'is_active' => 1,
            ])
            ->assertRedirect();

        $this->assertSame('Greeting v2', $response->fresh()->title);
    }

    public function test_sla_policy_is_gated_and_updatable(): void
    {
        $manager = $this->staffWithPermissions('sla_manager_'.uniqid(), ['support.manage_sla']);
        $viewer = $this->staffWithPermissions('sla_viewer_'.uniqid(), ['support.view']);

        $this->actingAs($manager)
            ->get(route('admin.support.sla.index'))
            ->assertOk();

        $this->actingAs($manager)
            ->put(route('admin.support.sla.update', 'urgent'), [
                'first_response_minutes' => 15,
                'resolution_minutes' => 120,
                'is_active' => 1,
            ])
            ->assertRedirect();

        $this->assertSame(15, SupportSlaPolicy::where('priority', 'urgent')->first()->first_response_minutes);

        $this->actingAs($viewer)
            ->put(route('admin.support.sla.update', 'low'), [
                'first_response_minutes' => 10,
                'resolution_minutes' => 20,
            ])
            ->assertForbidden();

        $this->assertSame(1440, SupportSlaPolicy::where('priority', 'low')->first()->first_response_minutes);
    }

    public function test_reply_records_first_response(): void
    {
        $agent = $this->staffWithPermissions('replier_'.uniqid(), ['support.view', 'support.reply']);
        $ticket = $this->ticket();

        $this->actingAs($agent)
            ->post(route('admin.support-tickets.reply', $ticket->id), ['body' => 'We are on it.'])
            ->assertRedirect();

        $this->assertNotNull($ticket->fresh()->first_responded_at);
        $this->assertDatabaseHas('support_ticket_messages', [
            'support_ticket_id' => $ticket->id,
            'sender_type' => 'staff',
            'body' => 'We are on it.',
        ]);
        $this->assertSame('pending', $ticket->fresh()->status);
    }

    public function test_internal_note_does_not_mark_first_response(): void
    {
        $agent = $this->staffWithPermissions('noter_'.uniqid(), ['support.view', 'support.reply']);
        $ticket = $this->ticket();
        $before = SupportTicketMessage::count();

        $this->actingAs($agent)
            ->post(route('admin.support-tickets.reply', $ticket->id), ['body' => 'Internal only', 'is_internal' => 1])
            ->assertRedirect();

        $this->assertNull($ticket->fresh()->first_responded_at);
        $this->assertSame('open', $ticket->fresh()->status);
        $this->assertSame($before + 1, SupportTicketMessage::count());
    }
}
