<?php

namespace Tests\Feature;

use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class PortalSubdomainTest extends TestCase
{
    use GrantsAdminAccess;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.admin_domain' => 'admin.juvo.test',
            'app.vendor_domain' => 'vendor.juvo.test',
        ]);
    }

    public function test_admin_subdomain_root_rewrites_to_admin_dashboard(): void
    {
        $this->get('http://admin.juvo.test/')
            ->assertRedirect(route('login'));
    }

    public function test_admin_subdomain_serves_login_form_without_redirecting(): void
    {
        $response = $this->get('http://admin.juvo.test/login');

        $response->assertOk();
        $this->assertNotSame(
            'https://admin.juvo.test/login',
            $response->headers->get('Location'),
            'The admin login must not redirect to itself.'
        );
    }

    public function test_admin_subdomain_serves_prefixed_routes(): void
    {
        $this->get('http://admin.juvo.test/dashboard')
            ->assertRedirect(route('login'));
    }

    public function test_vendor_subdomain_root_rewrites_to_vendor_dashboard(): void
    {
        $this->get('http://vendor.juvo.test/')
            ->assertRedirect(route('vendor.login'));
    }

    public function test_vendor_subdomain_serves_login_form(): void
    {
        $response = $this->get('http://vendor.juvo.test/login');

        $response->assertOk();
    }

    public function test_vendor_subdomain_serves_prefixed_routes(): void
    {
        $this->get('http://vendor.juvo.test/dashboard')
            ->assertRedirect(route('vendor.login'));
    }

    public function test_storefront_host_is_untouched(): void
    {
        $this->get('http://juvo.test/')->assertOk();
    }

    public function test_admin_subdomain_serves_admin_dashboard_for_signed_in_staff(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get('http://admin.juvo.test/')
            ->assertOk();
    }

    public function test_vendor_subdomain_serves_vendor_dashboard_for_signed_in_vendor(): void
    {
        $vendor = \App\Models\Vendor::query()->first();

        if (! $vendor) {
            $this->markTestSkipped('No vendor seeded.');
        }

        $this->actingAs($vendor, 'vendor')
            ->get('http://vendor.juvo.test/')
            ->assertOk();
    }
}
