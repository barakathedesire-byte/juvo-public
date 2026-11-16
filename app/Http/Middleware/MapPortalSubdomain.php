<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the admin and vendor portals from their own subdomains while keeping
 * the existing path-prefixed routes as the single source of truth.
 *
 * On `admin.<domain>` or `vendor.<domain>` the portal's own hosts are rewritten
 * before routing so that:
 *   - `/`               -> the portal dashboard (or its login when signed out)
 *   - `/login`          -> the portal login form
 *   - `/anything-else`  -> `/admin/anything-else` or `/vendor/anything-else`
 *
 * The storefront hosts are left untouched. The subdomain hosts are read from
 * config so the middleware is a no-op on localhost and in tests.
 */
class MapPortalSubdomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = Str::lower($request->getHost());

        $adminHost = Str::lower((string) config('app.admin_domain'));
        $vendorHost = Str::lower((string) config('app.vendor_domain'));

        $portal = null;
        $prefix = null;

        if ($adminHost !== '' && $host === $adminHost) {
            $portal = 'admin';
            $prefix = 'admin';
        } elseif ($vendorHost !== '' && $host === $vendorHost) {
            $portal = 'vendor';
            $prefix = 'vendor';
        }

        if ($portal === null) {
            return $next($request);
        }

        $path = ltrim($request->path(), '/');

        // Already carrying the portal prefix (e.g. /admin/... or /vendor/...).
        if ($path === '' || Str::startsWith($path, $prefix.'/') || $path === $prefix) {
            if ($path === '') {
                return $this->rewrite($request, $next, '/'.$prefix.'/dashboard');
            }

            return $next($request);
        }

        // The admin login and logout live at root ("/login", "/logout"), so on
        // the admin host they are served as-is. On the vendor host they live at
        // "/vendor/login" and "/vendor/logout".
        if ($path === 'login' || $path === 'logout') {
            if ($portal === 'vendor') {
                return $this->rewrite($request, $next, '/vendor/'.$path);
            }

            return $next($request);
        }

        // Everything else is rewritten onto the portal prefix.
        return $this->rewrite($request, $next, '/'.$prefix.'/'.$path);
    }

    /**
     * Dispatch the request as if it had arrived at the given path, leaving the
     * visitor's browser URL untouched.
     */
    protected function rewrite(Request $request, Closure $next, string $path): Response
    {
        $original = $request->server->all();

        $request->server->set('REQUEST_URI', $path);
        $request->server->set('ORIGINAL_REQUEST_URI', $original['REQUEST_URI'] ?? '/');

        $query = $request->getQueryString();

        $request->initialize(
            $request->query->all(),
            $request->request->all(),
            $request->attributes->all(),
            $request->cookies->all(),
            $request->files->all(),
            $request->server->all(),
            $request->getContent()
        );

        if ($query !== null) {
            $request->server->set('QUERY_STRING', $query);
        }

        return $next($request);
    }
}
