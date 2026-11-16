<?php

namespace Tests\Feature;

use App\Http\Middleware\EnforceCurrentSessionVersion;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Access\AccountStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class UnifiedAdminPortalTest extends TestCase
{
    use DatabaseTransactions;
    use GrantsAdminAccess;

    protected function staff(array $attrs = [], array $permissions = []): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $user = User::factory()->create(array_merge(['account_status' => AccountStatus::ACTIVE], $attrs));

        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    public function test_guest_is_redirected_to_the_single_login(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/login');
    }

    public function test_active_staff_can_sign_in_through_the_portal(): void
    {
        $user = $this->staff(['role' => 'viewer', 'email' => 'active@example.com'], ['dashboard.view']);

        $this->post('/login', ['email' => 'active@example.com', 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    /**
     * @dataProvider ineligibleStatuses
     */
    public function test_ineligible_account_states_cannot_sign_in(string $status): void
    {
        $email = 'blocked_'.$status.'@example.com';
        $this->staff(['role' => 'viewer', 'email' => $email, 'account_status' => $status], ['dashboard.view']);

        $this->post('/login', ['email' => $email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public static function ineligibleStatuses(): array
    {
        return [
            'invitation pending' => [AccountStatus::INVITATION_PENDING],
            'awaiting approval' => [AccountStatus::AWAITING_APPROVAL],
            'locked' => [AccountStatus::LOCKED],
            'disabled' => [AccountStatus::DISABLED],
            'suspended' => [AccountStatus::SUSPENDED],
            'revoked' => [AccountStatus::REVOKED],
        ];
    }

    public function test_account_without_portal_access_cannot_sign_in(): void
    {
        $email = 'no_portal@example.com';
        $this->staff([
            'role' => 'viewer',
            'email' => $email,
            'account_status' => AccountStatus::ACTIVE,
            'portal_access' => false,
        ], ['dashboard.view']);

        $this->post('/login', ['email' => $email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_vendor_credentials_cannot_authenticate_the_staff_portal(): void
    {
        $vendor = Vendor::create([
            'name' => 'Portal Vendor',
            'shop_name' => 'Portal Shop',
            'email' => 'vendorlogin@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
            'is_approved' => true,
            'email_verified_at' => now(),
        ]);

        // The vendor's own credentials are valid on the vendor guard...
        $this->assertTrue(Auth::guard('vendor')->attempt([
            'email' => $vendor->email,
            'password' => 'password',
        ]));

        // ...but the staff portal authenticates against the `users` provider only.
        $this->post('/login', ['email' => $vendor->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertNull(Auth::guard('web')->user());
    }

    public function test_login_redirects_to_a_permitted_workspace(): void
    {
        $this->staff(['role' => 'viewer', 'email' => 'support@example.com'], ['support.view']);

        $this->post('/login', ['email' => 'support@example.com', 'password' => 'password'])
            ->assertRedirect(route('admin.support-tickets.index'));

        $this->assertAuthenticated();
    }

    public function test_denied_panel_access_is_written_to_the_audit_trail(): void
    {
        $user = $this->staff(['role' => 'viewer'], ['support.view']);

        $this->actingAs($user)->get('/admin/vendors')->assertForbidden();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'access.denied',
            'actor_id' => $user->id,
        ]);
    }

    public function test_vendor_scoped_staff_cannot_reach_out_of_scope_records(): void
    {
        $inScope = $this->vendor();
        $outOfScope = $this->vendor();

        $user = $this->staff([
            'access_scope' => ['type' => 'vendors', 'ids' => [$inScope->id]],
        ], ['manage-vendors']);

        $this->actingAs($user)
            ->get(route('admin.vendors.show', $inScope->id))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('admin.vendors.show', $outOfScope->id))
            ->assertForbidden();
    }

    public function test_own_scope_staff_only_reach_their_own_tickets(): void
    {
        $vendor = $this->vendor();
        $user = $this->staff(['access_scope' => ['type' => 'tickets']], ['support.view']);
        $other = $this->staff(['role' => 'viewer'], ['support.view']);

        $mine = SupportTicket::create([
            'ticket_number' => 'TKT-'.strtoupper(uniqid()),
            'vendor_id' => $vendor->id,
            'guest_name' => 'Guest',
            'guest_email' => 'guest@example.com',
            'subject' => 'Mine',
            'category' => 'product_inquiry',
            'priority' => 'normal',
            'status' => 'open',
            'assigned_to' => $user->id,
        ]);

        $theirs = SupportTicket::create([
            'ticket_number' => 'TKT-'.strtoupper(uniqid()),
            'vendor_id' => $vendor->id,
            'guest_name' => 'Guest',
            'guest_email' => 'guest@example.com',
            'subject' => 'Theirs',
            'category' => 'product_inquiry',
            'priority' => 'normal',
            'status' => 'open',
            'assigned_to' => $other->id,
        ]);

        $this->actingAs($user)
            ->get(route('admin.support-tickets.show', $mine->id))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('admin.support-tickets.show', $theirs->id))
            ->assertForbidden();
    }

    public function test_bumping_auth_version_revokes_existing_sessions(): void
    {
        $user = $this->staff(['role' => 'viewer', 'email' => 'revoke@example.com'], ['dashboard.view']);

        $this->post('/login', ['email' => 'revoke@example.com', 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticated();

        // Simulate an access change (role/permission/status) that revokes sessions.
        $user->revokeSessions();

        // The guard caches the resolved user for the test process; a real request
        // re-resolves from the database, so drop the cache to mirror that.
        $this->app['auth']->forgetGuards();

        $this->get('/admin/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_admin_session_expires_after_absolute_24_hours(): void
    {
        $user = $this->staff(['role' => 'viewer', 'email' => 'expiry@example.com'], ['dashboard.view']);

        $this->post('/login', ['email' => 'expiry@example.com', 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));

        $this->get('/admin/dashboard')->assertOk();

        // Age the stamped sign-in time past the 24h absolute cap.
        $this->app['session']->put(
            'staff_login_at',
            now()->subMinutes(EnforceCurrentSessionVersion::ABSOLUTE_LIFETIME_MINUTES + 1)->timestamp
        );

        $this->get('/admin/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_admin_session_survives_within_the_absolute_window(): void
    {
        $user = $this->staff(['role' => 'viewer', 'email' => 'within@example.com'], ['dashboard.view']);

        $this->post('/login', ['email' => 'within@example.com', 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));

        $this->app['session']->put(
            'staff_login_at',
            now()->subMinutes(EnforceCurrentSessionVersion::ABSOLUTE_LIFETIME_MINUTES - 1)->timestamp
        );

        $this->get('/admin/dashboard')->assertOk();
        $this->assertAuthenticated();
    }

    public function test_revoking_portal_access_ends_the_account_sessions(): void
    {
        $admin = $this->admin();

        $target = $this->staff(['role' => 'viewer', 'email' => 'target@example.com'], ['dashboard.view']);
        $before = (int) $target->fresh()->auth_version;

        $this->actingAs($admin)
            ->post(route('admin.staff.revokeAccess', $target))
            ->assertRedirect();

        $target->refresh();
        $this->assertSame(AccountStatus::REVOKED, $target->account_status);
        $this->assertFalse((bool) $target->portal_access);
        $this->assertGreaterThan($before, (int) $target->auth_version);
    }

    public function test_approving_access_activates_a_pending_staff_member(): void
    {
        $admin = $this->admin();

        $pending = $this->staff([
            'role' => 'viewer',
            'account_status' => AccountStatus::AWAITING_APPROVAL,
            'portal_access' => false,
        ], ['dashboard.view']);

        $this->actingAs($admin)
            ->post(route('admin.staff.approveAccess', $pending))
            ->assertRedirect();

        $pending->refresh();
        $this->assertSame(AccountStatus::ACTIVE, $pending->account_status);
        $this->assertTrue((bool) $pending->portal_access);
        $this->assertTrue($pending->canAccessPortal());
    }

    /**
     * Create an in-scope vendor record for the scope tests.
     */
    protected function vendor(): Vendor
    {
        return Vendor::create([
            'name' => 'Scope Vendor',
            'shop_name' => 'Scope Shop',
            'email' => uniqid('scope').'@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
            'is_approved' => true,
            'email_verified_at' => now(),
        ]);
    }
}
