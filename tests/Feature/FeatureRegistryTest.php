<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\Feature;
use App\Models\User;
use App\Services\Admin\ApprovalService;
use App\Services\Features\FeatureFlagService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class FeatureRegistryTest extends TestCase
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

    protected function feature(array $attrs = []): Feature
    {
        return Feature::create(array_merge([
            'key' => 'flag_'.uniqid(),
            'name' => 'Test flag',
            'is_enabled' => false,
            'rollout_percentage' => 100,
        ], $attrs));
    }

    public function test_seeded_feature_is_enabled_and_unknown_key_fails_closed(): void
    {
        $service = app(FeatureFlagService::class);

        $this->assertTrue($service->isEnabled('sales_attribution'));
        $this->assertFalse($service->isEnabled('does_not_exist'));
        $this->assertTrue(feature_enabled('sales_attribution'));
        $this->assertFalse(feature_enabled('does_not_exist'));
    }

    public function test_rollout_percentage_gates_a_feature_deterministically(): void
    {
        $on = $this->feature(['is_enabled' => true, 'rollout_percentage' => 100]);
        $off = $this->feature(['is_enabled' => true, 'rollout_percentage' => 0]);
        $half = $this->feature(['is_enabled' => true, 'rollout_percentage' => 50]);

        $this->assertTrue($on->enabledFor(123));
        $this->assertFalse($off->enabledFor(123));
        $this->assertSame($half->enabledFor(123), $half->enabledFor(123));
        $this->assertFalse($this->feature(['is_enabled' => false])->enabledFor(123));
    }

    public function test_staff_with_view_permission_can_open_registry(): void
    {
        $staff = $this->staffWithPermissions('feature_reader_'.uniqid(), ['features.view']);

        $this->actingAs($staff)->get(route('admin.features.index'))->assertOk();
    }

    public function test_staff_without_view_permission_cannot_open_registry(): void
    {
        $staff = $this->staffWithPermissions('no_feature_'.uniqid(), ['orders.view']);

        $this->actingAs($staff)->get(route('admin.features.index'))->assertForbidden();
    }

    public function test_registering_a_feature_is_gated_and_audited(): void
    {
        $manager = $this->staffWithPermissions('feature_creator_'.uniqid(), ['features.view', 'features.create']);

        $this->actingAs($manager)
            ->post(route('admin.features.store'), [
                'key' => 'new_checkout',
                'name' => 'New checkout',
                'module' => 'checkout',
                'rollout_percentage' => 25,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('features', ['key' => 'new_checkout', 'rollout_percentage' => 25]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'feature.registered']);

        $viewer = $this->staffWithPermissions('feature_viewer_'.uniqid(), ['features.view']);
        $this->actingAs($viewer)
            ->post(route('admin.features.store'), ['key' => 'x', 'name' => 'X'])
            ->assertForbidden();
    }

    public function test_toggling_a_feature_requires_an_independent_approval(): void
    {
        $feature = $this->feature(['is_enabled' => false]);
        $actor = $this->staffWithPermissions('feature_toggler_'.uniqid(), ['features.view', 'features.toggle']);

        $this->actingAs($actor)
            ->post(route('admin.features.toggle', $feature->id), ['is_enabled' => 1, 'reason' => 'Enable pilot'])
            ->assertSessionHas('success');

        // Not applied yet — awaiting approval.
        $this->assertFalse($feature->fresh()->is_enabled);
        $pending = ApprovalRequest::where('ability', 'feature.toggle')->where('status', 'pending')->first();
        $this->assertNotNull($pending);

        // Independent approver signs off.
        app(ApprovalService::class)->approve($this->admin(), $pending);

        // Retry applies the change and closes the request.
        $this->actingAs($actor)
            ->post(route('admin.features.toggle', $feature->id), ['is_enabled' => 1])
            ->assertSessionHas('success');

        $this->assertTrue($feature->fresh()->is_enabled);
        $this->assertTrue(app(FeatureFlagService::class)->isEnabled($feature->key));
        $this->assertNotNull(ApprovalRequest::where('ability', 'feature.toggle')->whereNotNull('executed_at')->first());
        $this->assertDatabaseHas('audit_logs', ['action' => 'feature.toggled']);
    }

    public function test_prohibited_decision_blocks_a_toggle(): void
    {
        $feature = $this->feature(['is_enabled' => false]);
        $analyst = $this->staffWithPermissions('read_only_analyst', ['features.view', 'features.toggle']);

        $this->actingAs($actor = $analyst)
            ->post(route('admin.features.toggle', $feature->id), ['is_enabled' => 1])
            ->assertForbidden();

        $this->assertFalse($feature->fresh()->is_enabled);
    }

    public function test_staff_without_toggle_permission_cannot_toggle(): void
    {
        $feature = $this->feature(['is_enabled' => false]);
        $staff = $this->staffWithPermissions('no_toggle_'.uniqid(), ['features.view']);

        $this->actingAs($staff)
            ->post(route('admin.features.toggle', $feature->id), ['is_enabled' => 1])
            ->assertForbidden();
    }

    public function test_service_flush_updates_cached_state(): void
    {
        $feature = $this->feature(['is_enabled' => true]);
        $service = app(FeatureFlagService::class);

        $this->assertTrue($service->isEnabled($feature->key));

        $service->setEnabled($feature, false);
        $this->assertFalse($service->isEnabled($feature->key));
    }
}
