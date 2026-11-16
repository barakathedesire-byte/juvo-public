<?php

namespace Tests\Feature;

use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class VendorAccountStateTest extends TestCase
{
    use DatabaseTransactions;

    protected function vendor(string $status = 'active', bool $approved = true): Vendor
    {
        return Vendor::create([
            'name' => 'State Vendor',
            'shop_name' => 'State Shop',
            'email' => uniqid('state').'@example.com',
            'password' => Hash::make('password123'),
            'status' => $status,
            'is_approved' => $approved,
            'email_verified_at' => now(),
        ]);
    }

    public function test_pending_vendor_is_redirected_to_pending_page(): void
    {
        $vendor = $this->vendor('pending', false);

        $this->actingAs($vendor, 'vendor')
            ->get('/vendor/dashboard')
            ->assertRedirect(route('vendor.pending'));
    }

    public function test_suspended_vendor_cannot_access_any_protected_route(): void
    {
        $vendor = $this->vendor('suspended');

        foreach (['/vendor/dashboard', '/vendor/products', '/vendor/orders', '/vendor/finance', '/vendor/settings'] as $url) {
            $this->actingAs($vendor, 'vendor')
                ->get($url)
                ->assertRedirect(route('vendor.login'));
        }
    }

    public function test_restricted_vendor_cannot_access_panel(): void
    {
        $vendor = $this->vendor('restricted');

        $this->actingAs($vendor, 'vendor')
            ->get(route('vendor.dashboard'))
            ->assertRedirect(route('vendor.login'));
    }

    public function test_pending_vendor_cannot_bypass_restriction_via_api(): void
    {
        $vendor = $this->vendor('pending', false);

        $this->actingAs($vendor, 'vendor')
            ->post(route('vendor.orders.data'))
            ->assertRedirect(route('vendor.pending'));

        $this->actingAs($vendor, 'vendor')
            ->get(route('vendor.finance.transactions'))
            ->assertRedirect(route('vendor.pending'));
    }

    public function test_active_vendor_can_access_dashboard(): void
    {
        $vendor = $this->vendor('active', true);

        $this->actingAs($vendor, 'vendor')
            ->get(route('vendor.dashboard'))
            ->assertOk();
    }
}
