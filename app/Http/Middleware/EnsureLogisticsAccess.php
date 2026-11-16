<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLogisticsAccess
{
    /**
     * Map a logistics portal path segment to the module that governs it.
     */
    protected const MODULE_MAP = [
        '' => 'dashboard',
        'dashboard' => 'dashboard',
        'orders' => 'orders',
        'dispatch' => 'dispatch',
        'tracking' => 'tracking',
        'riders' => 'riders',
        'finance' => 'finance',
        'payouts' => 'payouts',
        'zones' => 'zones',
        'hubs' => 'zones',
        'pricing' => 'zones',
        'routes' => 'zones',
        'route-optimization' => 'zones',
        'analytics' => 'analytics',
        'reports' => 'analytics',
        'predictive' => 'analytics',
        'support' => 'support',
        'fraud' => 'fraud',
        'notifications' => 'notifications',
        'settings' => 'settings',
        'integration' => 'settings',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('logistics_admin');

        if (! $user) {
            abort(403);
        }

        if (! $user->canAccessPortal()) {
            abort(403);
        }

        $segments = explode('/', trim($request->path(), '/'));
        $segment = $segments[1] ?? '';

        if (in_array($segment, ['profile', 'logout'], true)) {
            return $next($request);
        }

        $module = self::MODULE_MAP[$segment] ?? null;

        if ($module !== null && ! $user->canAccessLogisticsModule($module)) {
            abort(403);
        }

        return $next($request);
    }
}
