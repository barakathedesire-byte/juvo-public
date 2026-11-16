<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\SalesLead;
use App\Models\SalesRep;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayout;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

/**
 * Cross-module authorization and public-endpoint hardening. These tests pin the
 * security boundary rather than a single module: guests cannot reach the admin
 * panel, each module write is permission-gated, and the public referral capture
 * cannot be abused.
 */
class SecurityHardeningTest extends TestCase
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

    public function test_guests_are_kept_out_of_the_admin_panel(): void
    {
        foreach ([
            'admin.dashboard',
            'admin.command-center.index',
            'admin.finance.index',
            'admin.features.index',
            'admin.roles.index',
            'admin.staff.index',
            'admin.audit-logs.index',
        ] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
    }

    public function test_module_writes_are_forbidden_without_the_required_permission(): void
    {
        $staff = $this->staffWithPermissions('low_priv_'.uniqid(), ['dashboard.view']);

        $this->actingAs($staff)->get(route('admin.finance.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.audit-logs.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.staff.index'))->assertForbidden();
        $this->actingAs($staff)->post(route('admin.features.store'), ['key' => 'x', 'name' => 'X'])->assertForbidden();
        $this->actingAs($staff)->post(route('admin.support.canned.store'), ['title' => 'Hi', 'body' => 'There'])->assertForbidden();

        $payout = VendorPayout::create([
            'vendor_id' => Vendor::query()->value('id') ?? 1,
            'reference' => 'PAY'.strtoupper(uniqid()),
            'amount' => 10,
        ]);
        $this->actingAs($staff)->post(route('admin.payouts.pay', $payout->id))->assertForbidden();
    }

    public function test_approving_requires_the_approve_permission_not_just_view(): void
    {
        $viewer = $this->staffWithPermissions('approval_viewer_'.uniqid(), ['approvals.view']);

        $approval = ApprovalRequest::create([
            'reference' => 'APR'.strtoupper(uniqid()),
            'ability' => 'feature.toggle',
            'module' => 'features',
        ]);

        $this->actingAs($viewer)->post(route('admin.approvals.approve', $approval->id))->assertForbidden();
    }

    public function test_role_creation_requires_the_create_permission(): void
    {
        $viewer = $this->staffWithPermissions('role_viewer_'.uniqid(), ['roles.view']);

        $this->actingAs($viewer)->post(route('admin.roles.store'), ['name' => 'escalated'])->assertForbidden();
        $this->assertDatabaseMissing('roles', ['name' => 'escalated']);
    }

    public function test_feature_creation_ignores_injected_primary_key(): void
    {
        $manager = $this->staffWithPermissions('feature_mass_'.uniqid(), ['features.view', 'features.create']);

        $this->actingAs($manager)
            ->post(route('admin.features.store'), [
                'id' => 999999,
                'key' => 'mass_assign_'.uniqid(),
                'name' => 'Injected',
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('features', ['id' => 999999]);
    }

    public function test_referral_capture_records_one_lead_per_session(): void
    {
        $rep = SalesRep::create(['code' => 'REP'.strtoupper(uniqid()), 'name' => 'Rep One', 'is_active' => true]);

        $this->get(route('referral.capture', $rep->code))->assertRedirect(route('xylo.home'));
        $this->get(route('referral.capture', $rep->code))->assertRedirect(route('xylo.home'));

        $this->assertSame(1, SalesLead::where('sales_rep_id', $rep->id)->count());
    }

    public function test_referral_capture_rejects_unknown_and_malformed_codes(): void
    {
        $this->get('/ref/UNKNOWNCODE')->assertRedirect(route('xylo.home'));
        $this->assertSame(0, SalesLead::where('notes', 'like', '%UNKNOWNCODE%')->count());

        $this->get('/ref/bad code!')->assertNotFound();
    }

    public function test_inactive_rep_code_does_not_capture(): void
    {
        $rep = SalesRep::create(['code' => 'OFF'.strtoupper(uniqid()), 'name' => 'Inactive', 'is_active' => false]);

        $this->get(route('referral.capture', $rep->code))->assertRedirect(route('xylo.home'));
        $this->assertSame(0, SalesLead::where('sales_rep_id', $rep->id)->count());
    }
}
