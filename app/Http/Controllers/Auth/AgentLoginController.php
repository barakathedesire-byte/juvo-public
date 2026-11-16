<?php

namespace App\Http\Controllers\Auth;

use App\Models\SalesRep;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AgentLoginController extends LoginController
{
    public function showLoginForm()
    {
        return view('agent.auth.login');
    }

    protected function attemptLogin(Request $request)
    {
        if (! parent::attemptLogin($request)) {
            return false;
        }

        /** @var User $user */
        $user = Auth::user();
        $agent = SalesRep::where('user_id', $user->id)->first();

        if (! $agent) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                $this->username() => ['This account does not have a JTeam sales profile.'],
            ]);
        }

        if (! $agent->canOperate()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                $this->username() => ['Your JTeam profile is not active yet. Contact the Juvo sales team.'],
            ]);
        }

        return true;
    }

    public function redirectTo(): string
    {
        return route('agent.dashboard');
    }
}
