<?php

namespace Tests\Feature;

use App\Models\Logistics\LogisticsAdmin;
use App\Services\Logistics\JuvoLogisticsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LogisticsIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_active_logistics_admin_can_login(): void
    {
        $admin = LogisticsAdmin::create([
            'name' => 'Active Logistics Admin',
            'email' => 'active-logistics-'.uniqid().'@example.com',
            'password' => Hash::make('ValidPass123!'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->post(route('logistics.login'), [
            'email' => $admin->email,
            'password' => 'ValidPass123!',
        ])->assertRedirect(route('logistics.dashboard'));

        $this->assertAuthenticatedAs($admin, 'logistics_admin');
    }

    public function test_inactive_logistics_admin_cannot_login(): void
    {
        $admin = LogisticsAdmin::create([
            'name' => 'Inactive Logistics Admin',
            'email' => 'inactive-logistics-'.uniqid().'@example.com',
            'password' => Hash::make('ValidPass123!'),
            'role' => 'admin',
            'is_active' => false,
        ]);

        $this->from(route('logistics.login'))
            ->post(route('logistics.login'), [
                'email' => $admin->email,
                'password' => 'ValidPass123!',
            ])
            ->assertRedirect(route('logistics.login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest('logistics_admin');
    }

    public function test_webhook_signature_requires_a_configured_secret_and_raw_body(): void
    {
        $service = app(JuvoLogisticsService::class);
        $payload = '{"event_type":"order.delivered","tracking_id":"trk-1"}';

        config(['juvo-logistics.webhook.secret' => null]);
        $this->assertFalse($service->verifyWebhook($payload, 'anything'));

        config(['juvo-logistics.webhook.secret' => 'test-secret']);
        $signature = hash_hmac('sha256', $payload, 'test-secret');

        $this->assertTrue($service->verifyWebhook($payload, 'sha256='.$signature));
        $this->assertFalse($service->verifyWebhook(json_encode(json_decode($payload, true)), $signature.'x'));
    }
}
