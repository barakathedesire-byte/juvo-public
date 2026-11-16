<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-side session invalidation. Each authenticated session records the
 * account's `auth_version` at login; when an administrator revokes sessions
 * (deactivation, credential reset, or a security-sensitive permission change)
 * the stored version is bumped, and every outstanding session is terminated on
 * its next request. Works with file, cookie and database session drivers.
 */
class EnforceCurrentSessionVersion
{
    /**
     * Absolute maximum admin session lifetime, in minutes. Staff sessions are
     * terminated this long after sign-in regardless of activity, so a stolen or
     * long-lived session can never outlive the window.
     */
    public const ABSOLUTE_LIFETIME_MINUTES = 1440;

    public function handle(Request $request, Closure $next): Response
    {
        $guard = $request->user('logistics_admin') ? 'logistics_admin' : null;
        $user = $guard ? $request->user($guard) : $request->user();

        if ($user) {
            $resolvedGuard = $guard ?: 'web';

            $issued = $request->session()->get('auth_version');

            if ($issued !== null && (int) $issued !== (int) $user->auth_version) {
                return $this->logoutWithMessage($request, 'Your session was ended because your access changed. Please sign in again.', $resolvedGuard);
            }

            $loginAt = $request->session()->get('staff_login_at');

            if ($loginAt !== null
                && (now()->timestamp - (int) $loginAt) >= self::ABSOLUTE_LIFETIME_MINUTES * 60) {
                return $this->logoutWithMessage($request, 'Your session has expired after 24 hours. Please sign in again.', $resolvedGuard);
            }
        }

        return $next($request);
    }

    protected function logoutWithMessage(Request $request, string $message, string $guard = 'web'): Response
    {
        Auth::guard($guard)->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            abort(401, $message);
        }

        $loginRoute = $guard === 'logistics_admin' ? 'logistics.login' : 'login';

        try {
            return redirect()->route($loginRoute)->withErrors(['email' => $message]);
        } catch (\Throwable) {
            return redirect('/login')->withErrors(['email' => $message]);
        }
    }
}
