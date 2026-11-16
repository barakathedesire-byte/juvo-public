<?php

if (! function_exists('storefront_url')) {
    /**
     * Absolute URL on the canonical storefront host.
     *
     * The admin and vendor subdomains are rewritten onto their own route
     * prefixes by MapPortalSubdomain, so a cross-portal link (for example the
     * "Buyer" link on the vendor login) must be absolute to escape that
     * rewrite and land on the main storefront host.
     */
    function storefront_url(string $path = '/'): string
    {
        return portal_absolute_url($path, (string) config('app.url'));
    }
}

if (! function_exists('admin_url')) {
    function admin_url(string $path = '/'): string
    {
        $domain = (string) config('app.admin_domain');

        return $domain !== '' ? portal_absolute_url($path, 'https://'.$domain) : url('/admin/'.ltrim($path, '/'));
    }
}

if (! function_exists('vendor_url')) {
    function vendor_url(string $path = '/'): string
    {
        $domain = (string) config('app.vendor_domain');

        return $domain !== '' ? portal_absolute_url($path, 'https://'.$domain) : url('/vendor/'.ltrim($path, '/'));
    }
}

if (! function_exists('portal_absolute_url')) {
    /**
     * Build an absolute URL on a specific origin. The path is appended to the
     * origin; an already absolute URL is returned unchanged.
     */
    function portal_absolute_url(string $path, string $origin): string
    {
        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        return rtrim($origin, '/').'/'.ltrim($path, '/');
    }
}
