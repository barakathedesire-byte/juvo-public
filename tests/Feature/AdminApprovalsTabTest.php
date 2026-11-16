<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class AdminApprovalsTabTest extends TestCase
{
    use DatabaseTransactions;
    use GrantsAdminAccess;

    protected function approver(string $roleName, array $permissions): User
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

    public function test_approvals_tab_renders_without_route_errors(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.approvals.index'))
            ->assertOk()
            ->assertSee('Approvals')
            ->assertSee(route('admin.approvals.data'), false);
    }

    public function test_approvals_data_endpoint_returns_json_for_reviewer(): void
    {
        $viewer = $this->approver('approvals_viewer_'.uniqid(), ['approvals.view']);

        $this->actingAs($viewer)
            ->getJson(route('admin.approvals.data', ['status' => 'all']))
            ->assertOk()
            ->assertJsonStructure(['data', 'recordsTotal', 'recordsFiltered']);
    }
}
