<?php
namespace Tests\Feature;
use Tests\TestCase;
class AgentAuthRedirectTest extends TestCase {
    public function test_agent_unauth_redirects_to_agent_login() {
        $this->get('/agent')->assertRedirect(route('agent.login'));
    }
}
