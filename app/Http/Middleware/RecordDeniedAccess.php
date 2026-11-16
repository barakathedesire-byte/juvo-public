<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Admin\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records denied access attempts for the security audit trail. Runs around the
 * admin route group; a 403 response from any downstream middleware or
 * controller (panel gate, permission middleware, inline authorize()) is logged
 * once with the acting staff member and the attempted path.
 */
class RecordDeniedAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() === 403 && $request->user() instanceof User) {
            try {
                AuditLogger::record('access.denied', null, [], [
                    'path' => $request->path(),
                    'method' => $request->method(),
                    'route' => optional($request->route())->getName(),
                ]);
            } catch (\Throwable) {
                // Never let auditing break the response.
            }
        }

        return $response;
    }
}
