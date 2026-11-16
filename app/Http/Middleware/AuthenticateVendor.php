<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticateVendor
{
    public function handle(Request $request, Closure $next)
    {
        if (! Auth::guard('vendor')->check()) {
            return redirect()->route('vendor.login');
        }

        $vendor = Auth::guard('vendor')->user();

        $logout = function (string $route, string $message) use ($request) {
            Auth::guard('vendor')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route($route)->with('error', $message);
        };

        if ($vendor->status === 'pending') {
            return $logout('vendor.pending', 'Your vendor account is pending approval.');
        }

        if ($vendor->status === 'inactive') {
            return $logout('vendor.login', 'Your store account is inactive. Please contact marketplace support.');
        }

        if ($vendor->status === 'suspended') {
            return $logout('vendor.login', 'Your store account has been suspended. Please contact marketplace support.');
        }

        if ($vendor->status === 'restricted') {
            return $logout('vendor.login', 'Your store account is restricted. Please contact marketplace support.');
        }

        if (in_array($vendor->status, ['rejected', 'banned'], true)) {
            return $logout('vendor.login', 'Your store account is not permitted to access the vendor panel.');
        }

        return $next($request);
    }
}
