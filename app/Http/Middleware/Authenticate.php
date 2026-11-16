<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson()) {
            return null;
        }

        if ($request->is('agent', 'agent/*')) {
            return route('agent.login');
        }

        if ($request->is('rider', 'rider/*')) {
            return route('rider.login');
        }

        if ($request->is('vendor', 'vendor/*')) {
            return route('vendor.login');
        }

        if ($request->is('customer', 'customer/*')) {
            return route('customer.login');
        }

        if ($request->is('logistics', 'logistics/*')) {
            return route('logistics.login');
        }

        return route('login');
    }
}
