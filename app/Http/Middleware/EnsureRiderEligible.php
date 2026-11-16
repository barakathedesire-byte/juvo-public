<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks the rider workspace until KYC is approved and the account is active,
 * so no rider can take or manage deliveries while ineligible.
 */
class EnsureRiderEligible
{
    public function handle(Request $request, Closure $next): Response
    {
        $rider = $request->user('rider');

        if (! $rider) {
            return redirect()->route('rider.login');
        }

        if (! $rider->isKycApproved()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your KYC must be approved before you can work.',
                ], 403);
            }

            return redirect()->route('rider.kyc.status');
        }

        if (! $rider->is_active) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your rider account is suspended.',
                ], 403);
            }

            return redirect()->route('rider.kyc.status')
                ->with('error', 'Your rider account is suspended. Contact Juvo support.');
        }

        return $next($request);
    }
}
