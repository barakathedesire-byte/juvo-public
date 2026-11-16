<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Access\AuthorizationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                return redirect($this->homeFor($guard));
            }
        }

        return $next($request);
    }

    protected function homeFor(?string $guard): string
    {
        return match ($guard) {
            'vendor' => route('vendor.dashboard'),
            'rider' => route('rider.dashboard'),
            'customer' => route('xylo.home'),
            default => $this->staffHome($guard),
        };
    }

    protected function staffHome(?string $guard): string
    {
        $user = Auth::guard($guard)->user();

        if ($user instanceof User && $user->canAccessPortal()) {
            return route(app(AuthorizationService::class)->landingRoute($user));
        }

        return route('xylo.home');
    }
}
