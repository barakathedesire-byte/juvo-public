<?php
namespace Tests\Feature;
use App\Models\SalesRep;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
class AgentCommissionCrmTest extends TestCase {
    use DatabaseTransactions;
    public function test_commissions_page_loads() {
        $user = User::factory()->create(['name'=>'C','email'=>'c'.uniqid().'@example.com','password'=>Hash::make('password'),'account_status'=>'active','portal_access'=>true]);
        SalesRep::create(['user_id'=>$user->id,'code'=>'AG'.uniqid(),'name'=>'C','email'=>$user->email,'commission_rate'=>5,'monthly_target'=>0,'is_active'=>true,'status'=>'approved','verification_status'=>'verified','currency'=>'TZS']);
        $this->actingAs($user)->get(route('agent.commissions.index'))->assertOk()->assertSee('Commissions & Bonuses');
    }
}
