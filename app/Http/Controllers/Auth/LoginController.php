<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Access\AuthorizationService;
use App\Services\Admin\AuditLogger;
use App\Support\Access\AccountStatus;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Centralized staff/admin portal login.
 *
 * Authentication is performed against the `web` guard (the staff `users`
 * provider) using the existing secure credential/session system. After the
 * credentials verify, the account must be in an eligible state and approved for
 * portal access; the target workspace is then derived from the account's
 * effective permissions. Customer and vendor portals use separate guards and
 * can never authenticate here.
 */
class LoginController extends Controller
{
    use AuthenticatesUsers;

    /**
     * The verified account that was denied portal access, so a clear but
     * non-disclosing message can be returned after a *valid* credential check.
     */
    protected ?User $denied = null;

    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    public function showLoginForm()
    {
        return view('admin.auth.login');
    }

    /**
     * Attempt the credentials, then require portal eligibility.
     */
    protected function attemptLogin(Request $request)
    {
        $guard = $this->guard();

        $ok = $guard->attempt(
            $this->credentials($request), false
        );

        if (! $ok) {
            return false;
        }

        /** @var User $user */
        $user = $guard->user();

        if (! $user->canAccessPortal()) {
            $this->denied = $user;

            $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return false;
        }

        return true;
    }

    /**
     * Clear throttle state, record the login, stamp version + last login.
     */
    protected function authenticated(Request $request, $user)
    {
        $request->session()->put('auth_version', (int) $user->auth_version);
        $request->session()->put('staff_login_at', now()->timestamp);

        if ($user->getRememberToken()) {
            $user->forceFill(['remember_token' => null])->saveQuietly();
        }

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        AuditLogger::record('auth.login', $user, [], ['ip' => $request->ip()], null, $user);
    }

    /**
     * Distinct, non-sensitive messaging: generic for bad credentials, clear for
     * a verified-but-ineligible account.
     */
    protected function sendFailedLoginResponse(Request $request)
    {
        if ($this->denied) {
            AuditLogger::record('auth.portal_denied', $this->denied, [], [
                'status' => $this->denied->account_status,
                'ip' => $request->ip(),
            ], null, $this->denied);

            throw ValidationException::withMessages([
                $this->username() => [AccountStatus::denialMessage($this->denied->account_status)],
            ]);
        }

        throw ValidationException::withMessages([
            $this->username() => [trans('auth.failed')],
        ]);
    }

    /**
     * Send the authenticated staff member to their best permitted workspace.
     */
    public function redirectTo(): string
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return route('login');
        }

        return route(app(AuthorizationService::class)->landingRoute($user));
    }

    public function logout(Request $request)
    {
        if ($user = $request->user()) {
            AuditLogger::record('auth.logout', $user, [], [], null, $user);
        }

        $this->guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
