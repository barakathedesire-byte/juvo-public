<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the admin panel. Access is granted only to staff whose account is
 * eligible (active) and explicitly approved for portal access, and who either
 * hold one of the legacy panel roles or have at least one granular admin
 * permission. Newly-created roles gain panel access through their permissions
 * rather than a hardcoded role list.
 */
class EnsureAdminPanelAccess
{
    /**
     * Roles that historically grant panel access even before granular
     * permissions are seeded.
     */
    public const LEGACY_PANEL_ROLES = [
        'super_admin', 'admin', 'manager', 'finance', 'support', 'ad_manager',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        if (! $user->canAccessPortal()) {
            abort(403);
        }

        if ($user->hasAnyRole(self::LEGACY_PANEL_ROLES)) {
            return $next($request);
        }

        if ($user->getAllPermissions()->isNotEmpty()) {
            return $next($request);
        }

        abort(403);
    }
}
