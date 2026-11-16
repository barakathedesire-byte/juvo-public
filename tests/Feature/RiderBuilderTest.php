<?php

namespace Tests\Feature;

use App\Models\HomepageSetting;
use App\Models\Logistics\LogisticsAdmin;
use App\Models\Rider\Rider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RiderBuilderTest extends TestCase
{
    use DatabaseTransactions;

    protected function logisticsAdmin(): LogisticsAdmin
    {
        return LogisticsAdmin::create([
            'name' => 'Rider Builder Admin',
            'email' => 'rider-builder-'.uniqid().'@example.com',
            'password' => Hash::make('ValidPass123!'),
            'role' => 'admin',
            'is_active' => true,
            'portal_access' => true,
            'account_status' => 'active',
        ]);
    }

    protected function rider(): Rider
    {
        return Rider::create([
            'first_name' => 'Builder',
            'last_name' => 'Rider',
            'email' => 'builder-rider-'.uniqid().'@example.com',
            'phone' => '072'.random_int(1000000, 9999999),
            'password' => 'password',
            'national_id' => 'NID-'.uniqid(),
            'date_of_birth' => '1995-01-01',
            'address' => '1 Rider Street',
            'city' => 'Dar es Salaam',
            'state' => 'Dar es Salaam',
            'license_number' => 'LIC-'.uniqid(),
            'license_expiry' => now()->addYear()->toDateString(),
            'kyc_status' => 'approved',
            'is_active' => true,
            'background_check_status' => 'clear',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        HomepageSetting::where('key', 'rider_builder')->delete();
    }

    public function test_rider_builder_page_loads_for_logistics_admin(): void
    {
        $admin = $this->logisticsAdmin();

        $this->actingAs($admin, 'logistics_admin')
            ->get(route('logistics.rider-builder.index'))
            ->assertOk()
            ->assertSee('Rider Builder')
            ->assertSee('Bottom navigation');
    }

    public function test_rider_builder_updates_rider_app_output(): void
    {
        $admin = $this->logisticsAdmin();

        $this->actingAs($admin, 'logistics_admin')
            ->post(route('logistics.rider-builder.update'), [
                'brand_name' => 'Custom Rider',
                'brand_tagline' => 'Custom tagline',
                'brand_icon' => 'fas fa-bicycle',
                'app_title' => 'Custom Rider App',
                'primary_color' => '#123456',
                'primary_dark' => '#0f2a44',
                'primary_light' => '#ddeeff',
                'canvas_color' => '#f0f4f8',
                'success_color' => '#00aa55',
                'danger_color' => '#cc0033',
                'support_phone' => '+255700000000',
                'support_email' => 'rider-support@example.com',
                'hero_greeting_suffix' => 'Custom greeting suffix',
                'dashboard_earnings_label' => 'Custom earnings label',
                'empty_assignment_copy' => 'Custom empty assignment copy',
                'features' => [
                    'show_earnings' => '1',
                    'show_orders' => '1',
                    'show_profile' => '1',
                    'show_notifications' => '1',
                    'show_availability' => '1',
                    'show_cod' => '1',
                    'show_map' => '1',
                ],
                'navigation' => [
                    ['key' => 'dashboard', 'label' => 'Home', 'icon' => 'fas fa-house', 'enabled' => '1'],
                    ['key' => 'orders', 'label' => 'Jobs', 'icon' => 'fas fa-box', 'enabled' => '1'],
                    ['key' => 'earnings', 'label' => 'Money', 'icon' => 'fas fa-wallet', 'enabled' => '1'],
                    ['key' => 'profile', 'label' => 'Me', 'icon' => 'far fa-user', 'enabled' => '1'],
                ],
            ])
            ->assertRedirect(route('logistics.rider-builder.index'));

        $rider = $this->rider();

        $html = $this->actingAs($rider, 'rider')
            ->get(route('rider.profile'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Custom Rider', $html);
        $this->assertStringContainsString('#123456', $html);
        $this->assertStringContainsString('rider-support@example.com', $html);
    }
}
