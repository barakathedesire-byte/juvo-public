<?php

namespace Tests\Feature;

use App\Models\Feature;
use App\Models\Order;
use App\Models\User;
use App\Services\Admin\PlatformIntegrityService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class PlatformIntegrityTest extends TestCase
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

    public function test_service_returns_a_structured_checklist(): void
    {
        $checks = app(PlatformIntegrityService::class)->checks();

        $this->assertNotEmpty($checks);

        $keys = array_column($checks, 'key');
        $this->assertContains('order_totals', $keys);
        $this->assertContains('commission_math', $keys);
        $this->assertContains('support_sla', $keys);
        $this->assertContains('attribution_math', $keys);

        foreach ($checks as $check) {
            $this->assertArrayHasKey('label', $check);
            $this->assertIsInt($check['count']);
            $this->assertContains($check['status'], ['ok', 'warn', 'fail']);
        }
    }

    public function test_it_detects_an_unbalanced_order_total(): void
    {
        Order::create([
            'total_amount' => 111.11,
            'subtotal' => 50.00,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'delivery_fee' => 0,
            'status' => 'pending',
        ]);

        $check = collect(app(PlatformIntegrityService::class)->checks())->firstWhere('key', 'order_totals');

        $this->assertSame('fail', $check['status']);
        $this->assertGreaterThanOrEqual(1, $check['count']);
    }

    public function test_it_flags_an_out_of_range_feature_rollout(): void
    {
        Feature::create([
            'key' => 'bad_rollout_'.uniqid(),
            'name' => 'Bad rollout',
            'is_enabled' => true,
            'rollout_percentage' => 150,
        ]);

        $check = collect(app(PlatformIntegrityService::class)->checks())->firstWhere('key', 'feature_registry');

        $this->assertSame('warn', $check['status']);
        $this->assertGreaterThanOrEqual(1, $check['count']);
    }

    public function test_reconcile_command_fails_in_strict_mode_when_an_invariant_breaks(): void
    {
        $this->artisan('platform:reconcile')->assertExitCode(0);

        Order::create([
            'total_amount' => 999.99,
            'subtotal' => 1.00,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'delivery_fee' => 0,
            'status' => 'pending',
        ]);

        $this->artisan('platform:reconcile --strict')->assertExitCode(1);
    }

    public function test_integrity_page_is_permission_gated(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.integrity.index'))
            ->assertOk()
            ->assertSee('Integrity');

        $auditor = $this->staffWithPermissions('auditor_'.uniqid(), ['audit.view']);
        $this->actingAs($auditor)->get(route('admin.integrity.index'))->assertOk();

        $outsider = $this->staffWithPermissions('no_integrity_'.uniqid(), ['dashboard.view']);
        $this->actingAs($outsider)->get(route('admin.integrity.index'))->assertForbidden();
    }
}
