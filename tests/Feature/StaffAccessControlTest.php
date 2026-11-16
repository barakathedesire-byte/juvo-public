<?php

namespace Tests\Feature;

use App\Models\ApprovalPolicy;
use App\Models\RoleDecisionPolicy;
use App\Models\User;
use App\Services\Admin\ApprovalService;
use App\Services\Admin\AuditLogger;
use App\Services\Admin\DecisionAuthority;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class StaffAccessControlTest extends TestCase
{
    use DatabaseTransactions;
    use GrantsAdminAccess;

    protected function permission(string $name): Permission
    {
        return Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    protected function staffWithRole(string $roleName, array $permissions = []): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

        foreach ($permissions as $permission) {
            $this->permission($permission);
        }

        $user = User::factory()->create(['role' => $roleName]);
        $user->assignRole($role);

        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    public function test_super_admin_bypasses_individual_permissions(): void
    {
        $admin = $this->admin();

        $this->assertTrue($admin->can('roles.manage_permissions'));
        $this->assertTrue($admin->can('anything.at.all'));
    }

    public function test_staff_without_permission_cannot_view_roles(): void
    {
        $user = $this->staffWithRole('salesperson');

        $this->actingAs($user)
            ->get(route('admin.roles.index'))
            ->assertForbidden();
    }

    public function test_staff_with_roles_view_permission_can_list_roles(): void
    {
        $user = $this->staffWithRole('read_only_analyst', ['roles.view']);

        $this->actingAs($user)
            ->get(route('admin.roles.index'))
            ->assertOk();
    }

    public function test_protected_role_cannot_be_deleted(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->delete(route('admin.roles.destroy', Role::where('name', 'admin')->firstOrFail()))
            ->assertSessionHasErrors('role');

        $this->assertDatabaseHas('roles', ['name' => 'admin']);
    }

    public function test_role_assigned_to_staff_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $role = Role::firstOrCreate(['name' => 'temp_role_'.uniqid(), 'guard_name' => 'web']);
        User::factory()->create(['role' => $role->name])->assignRole($role);

        $this->actingAs($admin)
            ->delete(route('admin.roles.destroy', $role))
            ->assertSessionHasErrors('role');

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_creating_a_role_persists_permissions_and_decision_policy(): void
    {
        $admin = $this->admin();
        $this->permission('vendors.adjust');

        $name = 'finance_officer_'.uniqid();

        $this->actingAs($admin)->post(route('admin.roles.store'), [
            'name' => $name,
            'permissions' => ['vendors.adjust'],
            'policies' => [
                'finance.adjustment.create' => ['mode' => 'limited', 'limit' => 1000],
            ],
        ])->assertRedirect(route('admin.roles.index'));

        $role = Role::where('name', $name)->firstOrFail();
        $this->assertTrue($role->hasPermissionTo('vendors.adjust'));

        $this->assertDatabaseHas('role_decision_policies', [
            'role_id' => $role->id,
            'ability' => 'finance.adjustment.create',
            'mode' => 'limited',
        ]);
    }

    public function test_decision_authority_allows_within_limit_and_flags_over_limit(): void
    {
        $user = $this->staffWithRole('fin_limit_'.uniqid(), ['vendors.adjust']);
        $role = $user->roles->first();

        RoleDecisionPolicy::create([
            'role_id' => $role->id,
            'ability' => 'finance.adjustment.create',
            'mode' => 'limited',
            'limits' => ['max_amount' => 1000],
        ]);

        $authority = app(DecisionAuthority::class);

        $under = $authority->evaluate($user, 'finance.adjustment.create', 500);
        $this->assertTrue($under['allowed']);
        $this->assertTrue($under['can_finalize']);

        $over = $authority->evaluate($user, 'finance.adjustment.create', 5000);
        $this->assertTrue($over['requires_approval']);
        $this->assertFalse($over['can_finalize']);
    }

    public function test_decision_authority_blocks_prohibited_operations(): void
    {
        $user = $this->staffWithRole('fin_blocked_'.uniqid(), ['vendors.adjust']);
        $role = $user->roles->first();

        RoleDecisionPolicy::create([
            'role_id' => $role->id,
            'ability' => 'finance.adjustment.create',
            'mode' => 'prohibited',
            'limits' => null,
        ]);

        $result = app(DecisionAuthority::class)->evaluate($user, 'finance.adjustment.create', 100);

        $this->assertFalse($result['allowed']);
        $this->assertSame(DecisionAuthority::PROHIBITED, $result['mode']);
    }

    public function test_decision_authority_prohibits_without_permission(): void
    {
        $user = $this->staffWithRole('fin_noperm_'.uniqid());

        $result = app(DecisionAuthority::class)->evaluate($user, 'finance.adjustment.create', 100);

        $this->assertFalse($result['has_permission']);
        $this->assertFalse($result['allowed']);
    }

    public function test_approval_engine_enforces_separation_of_duties(): void
    {
        $requester = $this->staffWithRole('finance_officer', ['vendors.adjust']);

        ApprovalPolicy::updateOrCreate(['key' => 'finance.adjustment.create'], [
            'name' => 'Adjustment',
            'module' => 'finance',
            'requires_approval' => true,
            'stages' => 1,
            'approver_permission' => 'finance.reconcile',
            'risk' => 'high',
            'is_active' => true,
        ]);

        $service = app(ApprovalService::class);

        $request = $service->submit($requester, 'finance.adjustment.create', null, ['amount' => 5000], 'Test adjustment', 5000);
        $this->assertSame('pending', $request->status);
        $this->assertTrue($request->isOpen());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Separation of duties');
        $service->approve($requester, $request, 'self approve');
    }

    public function test_authorized_approver_can_approve_a_request(): void
    {
        $requester = $this->staffWithRole('finance_officer', ['vendors.adjust']);
        $approver = $this->staffWithRole('finance_approver', ['finance.reconcile']);

        $service = app(ApprovalService::class);
        $request = $service->submit($requester, 'finance.adjustment.create', null, ['amount' => 5000], 'Test adjustment', 5000);

        $approved = $service->approve($approver, $request, 'Looks good');

        $this->assertSame('approved', $approved->status);
        $this->assertSame($approver->id, $approved->decided_by);
        $this->assertDatabaseHas('approval_request_steps', [
            'approval_request_id' => $request->id,
            'actor_id' => $approver->id,
            'action' => 'approved',
        ]);
    }

    public function test_unauthorized_user_cannot_approve(): void
    {
        $requester = $this->staffWithRole('finance_officer', ['vendors.adjust']);
        $outsider = $this->staffWithRole('salesperson');

        $service = app(ApprovalService::class);
        $request = $service->submit($requester, 'finance.adjustment.create', null, [], 'Test', 5000);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not authorized');
        $service->approve($outsider, $request, 'nope');
    }

    public function test_http_approval_decision_flows_through_controller(): void
    {
        $requester = $this->staffWithRole('finance_officer', ['vendors.adjust']);
        $approver = $this->staffWithRole('finance_approver', ['approvals.approve', 'finance.reconcile']);

        $request = app(ApprovalService::class)->submit($requester, 'finance.adjustment.create', null, ['amount' => 5000], 'Test', 5000);

        $this->actingAs($approver)
            ->post(route('admin.approvals.approve', $request), ['reason' => 'Approved'])
            ->assertSessionHasNoErrors();

        $this->assertSame('approved', $request->fresh()->status);
    }

    public function test_self_approval_is_rejected_over_http(): void
    {
        $requester = $this->staffWithRole('finance_officer', ['vendors.adjust', 'approvals.approve']);

        $request = app(ApprovalService::class)->submit($requester, 'finance.adjustment.create', null, ['amount' => 5000], 'Mine', 5000);

        $this->actingAs($requester)
            ->post(route('admin.approvals.approve', $request))
            ->assertSessionHasErrors('approval');

        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_audit_logger_writes_append_only_records(): void
    {
        $admin = $this->admin();

        app(AuditLogger::class)->log('test.action', $admin, [], ['key' => 'value'], 'Because', $admin);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'test.action',
            'actor_id' => $admin->id,
            'reason' => 'Because',
        ]);
    }

    public function test_audit_log_index_is_permission_gated(): void
    {
        $denied = $this->staffWithRole('salesperson');
        $this->actingAs($denied)->get(route('admin.audit-logs.index'))->assertForbidden();

        $allowed = $this->staffWithRole('compliance_officer', ['audit.view']);
        $this->actingAs($allowed)->get(route('admin.audit-logs.index'))->assertOk();
    }

    public function test_staff_without_any_permission_or_panel_role_is_denied_panel(): void
    {
        $user = $this->staffWithRole('staff');

        $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_staff_with_granular_permission_reaches_the_panel(): void
    {
        $user = $this->staffWithRole('read_only_analyst', ['dashboard.view']);

        $this->actingAs($user)->get(route('admin.dashboard'))->assertOk();
    }

    public function test_suspended_staff_is_denied_panel(): void
    {
        $user = $this->staffWithRole('compliance_officer');
        $user->forceFill(['account_status' => 'suspended'])->save();

        $this->actingAs($user->fresh())->get(route('admin.dashboard'))->assertForbidden();
    }
}
