<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class SecurityRbacTest extends TestCase
{
    use DatabaseTransactions, GrantsAdminAccess;

    protected function userWithRole(string $role): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create(['role' => $role]);
        $user->assignRole(Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    public function test_guest_is_redirected_from_admin_panel(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/login');
        $this->get('/site-settings')->assertRedirect('/login');
    }

    public function test_super_admin_can_reach_financial_and_settings_areas(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/dashboard')->assertOk();
        $this->actingAs($admin)->get('/admin/payments')->assertOk();
        $this->actingAs($admin)->get('/site-settings')->assertOk();
    }

    public function test_support_role_is_denied_financial_and_settings_areas(): void
    {
        $support = $this->userWithRole('support');

        $this->actingAs($support)->get('/admin/dashboard')->assertOk();
        $this->actingAs($support)->get('/admin/payments')->assertForbidden();
        $this->actingAs($support)->get('/admin/vendors')->assertForbidden();
        $this->actingAs($support)->get('/site-settings')->assertForbidden();
    }

    public function test_user_without_any_panel_role_is_denied_admin_panel(): void
    {
        $stranger = User::factory()->create(['role' => 'viewer']);

        $this->actingAs($stranger)->get('/admin/dashboard')->assertForbidden();
    }

    public function test_public_admin_registration_route_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
    }
}
