<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class VendorAuthGuestAccessTest extends TestCase
{
    use DatabaseTransactions, GrantsAdminAccess;

    public function test_guest_can_open_vendor_login_and_register(): void
    {
        $this->get(route('vendor.login'))->assertOk();
        $this->get(route('vendor.register'))->assertOk();
    }

    public function test_authenticated_staff_can_still_open_vendor_login_and_register(): void
    {
        $staff = $this->admin();

        $this->actingAs($staff, 'web')->get(route('vendor.login'))->assertOk();
        $this->actingAs($staff, 'web')->get(route('vendor.register'))->assertOk();
    }

    public function test_authenticated_staff_is_redirected_off_admin_login_to_a_live_page(): void
    {
        $staff = $this->admin();

        $response = $this->actingAs($staff, 'web')->get('/login');

        $response->assertRedirect();
        $this->assertNotSame(url('/home'), $response->headers->get('Location'));

        $this->actingAs($staff, 'web')
            ->get($response->headers->get('Location'))
            ->assertOk();
    }
}
