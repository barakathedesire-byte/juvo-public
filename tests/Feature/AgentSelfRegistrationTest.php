<?php

namespace Tests\Feature;

use App\Models\SalesRep;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AgentSelfRegistrationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_agent_can_self_register_with_required_fields(): void
    {
        $this->post(route('agent.register.post'), [
            'name' => 'Self Agent',
            'email' => 'self-agent-'.uniqid().'@example.com',
            'phone' => '0712345678',
            'national_id' => 'NID12345',
            'address' => '123 Main Street',
            'next_of_kin_name' => 'Next Kin',
            'next_of_kin_phone' => '0798765432',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'terms' => '1',
        ])->assertRedirect(route('agent.login'));

        $user = User::where('email', 'LIKE', 'self-agent-%')->first();
        $this->assertNotNull($user);
        $agent = SalesRep::where('user_id', $user->id)->first();
        $this->assertNotNull($agent);
        $this->assertSame('applied', $agent->status);
        $this->assertSame('0712345678', $agent->phone);
        $this->assertSame('NID12345', $agent->national_id);
        $this->assertSame('Next Kin', $agent->next_of_kin_name);
    }
}
