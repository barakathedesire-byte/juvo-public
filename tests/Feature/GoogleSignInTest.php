<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class GoogleSignInTest extends TestCase
{
    use DatabaseTransactions;

    protected function fakeGoogleUser(string $id, string $email, string $name = 'Google Person'): void
    {
        $user = (new SocialiteUser)->map([
            'id' => $id,
            'email' => $email,
            'name' => $name,
            'nickname' => $name,
        ]);

        Socialite::shouldReceive('driver')->with('google')->andReturnSelf();
        Socialite::shouldReceive('user')->andReturn($user);
    }

    public function test_google_redirect_sends_user_to_google(): void
    {
        Socialite::shouldReceive('driver')->with('google')->andReturnSelf();
        Socialite::shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));

        $this->get(route('customer.auth.google.redirect'))
            ->assertRedirect('https://accounts.google.com/o/oauth2/auth');
    }

    public function test_callback_creates_a_customer_and_signs_in(): void
    {
        $email = 'new-google-'.uniqid().'@example.com';
        $this->fakeGoogleUser('google-'.uniqid(), $email);

        $this->get(route('customer.auth.google.callback'))
            ->assertRedirect(route('xylo.home'));

        $this->assertDatabaseHas('customers', [
            'email' => $email,
            'status' => 1,
        ]);
    }

    public function test_callback_links_google_id_to_an_existing_customer(): void
    {
        $customer = Customer::create([
            'name' => 'Existing Buyer',
            'email' => 'existing-google-'.uniqid().'@example.com',
            'password' => 'password123',
            'status' => 1,
        ]);

        $this->fakeGoogleUser('google-linked-'.uniqid(), $customer->email);

        $this->get(route('customer.auth.google.callback'))
            ->assertRedirect(route('xylo.home'));

        $this->assertNotNull($customer->fresh()->google_id);
        $this->assertSame(1, Customer::where('email', $customer->email)->count());
    }
}
