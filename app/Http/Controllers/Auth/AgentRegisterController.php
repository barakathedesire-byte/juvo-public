<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SalesRep;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AgentRegisterController extends Controller
{
    public function showRegistrationForm()
    {
        return view('agent.auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|max:30',
            'national_id' => 'required|string|max:50',
            'address' => 'required|string|max:1000',
            'next_of_kin_name' => 'required|string|max:255',
            'next_of_kin_phone' => 'required|string|max:30',
            'password' => ['required', 'confirmed', Password::min(8)],
            'terms' => 'accepted',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'account_status' => 'awaiting_approval',
            'portal_access' => true,
        ]);

        SalesRep::create([
            'user_id' => $user->id,
            'code' => 'AG'.strtoupper(substr(md5(uniqid('', true)), 0, 8)),
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'national_id' => $data['national_id'],
            'address' => $data['address'],
            'next_of_kin_name' => $data['next_of_kin_name'],
            'next_of_kin_phone' => $data['next_of_kin_phone'],
            'status' => 'applied',
            'verification_status' => 'unverified',
            'is_active' => false,
            'commission_rate' => 0,
            'monthly_target' => 0,
            'currency' => 'TZS',
            'terms_version' => 'v1',
            'terms_accepted_at' => now(),
            'applied_at' => now(),
        ]);

        return redirect()->route('agent.login')
            ->with('success', 'Registration submitted. Your JTeam account will be reviewed and activated by Juvo.');
    }
}
