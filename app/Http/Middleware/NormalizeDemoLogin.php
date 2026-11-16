<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizeDemoLogin
{
    /**
     * Allow the public demo to sign in with "admin" instead of an email.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('demo.enabled') || ! $request->isMethod('post')) {
            return $next($request);
        }

        $loginPaths = [
            'login',
            'admin/login',
            'agent/login',
            'vendor/login',
            'customer/login',
            'logistics/login',
            'rider/login',
        ];

        if (! in_array(trim($request->path(), '/'), $loginPaths, true)) {
            return $next($request);
        }

        $username = strtolower(trim((string) $request->input('email')));

        if ($username === strtolower((string) config('demo.login'))) {
            $request->merge(['email' => config('demo.email')]);
        }

        return $next($request);
    }
}
