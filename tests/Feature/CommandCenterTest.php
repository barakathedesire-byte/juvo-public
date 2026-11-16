<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Admin\PlatformReportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class CommandCenterTest extends TestCase
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

    public function test_admin_opens_command_center_with_all_modules(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.command-center.index'))
            ->assertOk()
            ->assertSee('SLA breached')
            ->assertSee('Platform revenue')
            ->assertSee('Pending approvals');
    }

    public function test_staff_without_any_module_permission_is_forbidden(): void
    {
        $staff = $this->staffWithPermissions('no_module_'.uniqid(), ['orders.edit']);

        $this->actingAs($staff)
            ->get(route('admin.command-center.index'))
            ->assertForbidden();
    }

    public function test_panels_are_limited_to_modules_the_staff_can_access(): void
    {
        $support = $this->staffWithPermissions('support_only_'.uniqid(), ['support.view']);

        $response = $this->actingAs($support)->get(route('admin.command-center.index'));
        $response->assertOk();

        $panels = $response->viewData('panels');
        $this->assertTrue($panels['support']);
        $this->assertFalse($panels['finance']);
        $this->assertFalse($panels['sales']);
        $this->assertFalse($panels['governance']);

        $response->assertSee('SLA breached')->assertDontSee('Platform revenue');
    }

    public function test_overview_service_returns_a_full_snapshot(): void
    {
        $overview = app(PlatformReportService::class)->overview();

        foreach (['commerce', 'finance', 'support', 'sales', 'governance'] as $module) {
            $this->assertArrayHasKey($module, $overview);
        }

        $this->assertIsInt($overview['commerce']['orders']);
        $this->assertIsFloat($overview['commerce']['revenue']);
        $this->assertIsInt($overview['support']['open']);
        $this->assertIsInt($overview['governance']['pending_approvals']);
        $this->assertArrayHasKey('attributed_total', $overview['sales']);
    }

    public function test_activity_feed_is_returned_for_the_governance_panel(): void
    {
        $activity = app(PlatformReportService::class)->activity();

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $activity);
        $activity->each(fn ($row) => $this->assertArrayHasKey('action', $row));
    }
}
