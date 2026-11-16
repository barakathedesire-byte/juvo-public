<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use DatabaseTransactions;
    use GrantsAdminAccess;

    protected function staff(string $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        $user->syncRoles([Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'])->name]);

        return $user;
    }

    public function test_super_admin_can_view_the_staff_list(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.staff.index'))
            ->assertOk();
    }

    public function test_super_admin_can_create_a_staff_account_with_a_role(): void
    {
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.staff.store'), [
            'name' => 'New Manager',
            'email' => 'new-manager-'.uniqid().'@example.com',
            'password' => 'password123',
            'role' => 'manager',
        ])->assertRedirect(route('admin.staff.index'));

        $user = User::where('name', 'New Staff Member')->orWhere('name', 'New Manager')->latest('id')->firstOrFail();
        $this->assertTrue($user->hasRole('manager'));
        $this->assertSame('manager', $user->role);
    }

    public function test_non_super_admin_cannot_manage_staff(): void
    {
        Role::firstOrCreate(['name' => 'support', 'guard_name' => 'web']);

        $staff = User::factory()->create(['role' => 'support']);
        $staff->assignRole('support');

        $this->actingAs($staff)->get(route('admin.staff.index'))->assertForbidden();
        $this->actingAs($staff)->post(route('admin.staff.store'), [])->assertForbidden();
    }

    public function test_super_admin_cannot_delete_their_own_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->delete(route('admin.staff.destroy', $admin->id))
            ->assertSessionHasErrors('staff');

        $this->assertNotNull(User::find($admin->id));
    }

    public function test_super_admin_can_update_a_staff_role(): void
    {
        $admin = $this->admin();
        Role::firstOrCreate(['name' => 'finance', 'guard_name' => 'web']);

        $staff = User::factory()->create(['role' => 'support', 'password' => Hash::make('password')]);
        $staff->assignRole('support');

        $this->actingAs($admin)->put(route('admin.staff.update', $staff->id), [
            'name' => 'Renamed Staff',
            'email' => $staff->email,
            'role' => 'finance',
        ])->assertRedirect(route('admin.staff.index'));

        $staff->refresh();
        $this->assertSame('finance', $staff->role);
        $this->assertTrue($staff->hasRole('finance'));
        $this->assertFalse($staff->hasRole('support'));
    }

    public function test_creating_staff_rejects_a_duplicate_email(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->create(['role' => 'support']);

        $this->actingAs($admin)->post(route('admin.staff.store'), [
            'name' => 'Dup',
            'email' => $staff->email,
            'password' => 'password123',
            'role' => 'support',
        ])->assertSessionHasErrors('email');
    }
}
