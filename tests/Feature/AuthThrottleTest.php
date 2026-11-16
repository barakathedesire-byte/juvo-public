<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AuthThrottleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_vendor_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post('/vendor/login', [
                'email' => 'nobody@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $this->post('/vendor/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_customer_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post('/customer/login', [
                'email' => 'nobody@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $this->post('/customer/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_api_customer_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/customer/login', [
                'email' => 'nobody@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $this->postJson('/api/customer/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_staff_self_service_password_reset_is_not_routed(): void
    {
        $this->assertFalse(app('router')->getRoutes()->hasNamedRoute('password.request'));
        $this->assertFalse(app('router')->getRoutes()->hasNamedRoute('password.reset'));

        $this->get('/password/reset')->assertNotFound();
    }

    public function test_admin_login_page_has_no_forgot_password_link(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertDontSee('password.request')
            ->assertDontSee('Forgot password');
    }
}
