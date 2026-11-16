<?php

namespace App\Services\Pages;

/**
 * Central catalogue of every page surfaced in the admin Pages dashboard.
 *
 * Two kinds of entries:
 *  - Persistent pages (rows in `pages`, whether authored custom pages or
 *    registered system template pages) — editable through the page builder.
 *  - Virtual entries: dynamic routes and per-record instances (each category,
 *    each collection) shown read-only with links to their real editor. These
 *    are never stored as duplicate editable pages.
 */
class PageRegistry
{
    /**
     * System pages that live in the `pages` table so their safe content
     * regions can be edited by the page builder.
     *
     * @return array<string, array{name: string, type: string, url: ?string, blocks: array<int, string>}>
     */
    public static function systemDefinitions(): array
    {
        return [
            'home' => [
                'name' => 'Homepage',
                'type' => 'system',
                'url' => '/',
                'region' => false,
            ],
            'shop' => [
                'name' => 'Product Listing',
                'type' => 'system',
                'url' => '/products',
                'region' => true,
                'blocks' => ['promo_banner', 'notice', 'text', 'image', 'heading', 'cta', 'countdown', 'category_tiles', 'category_grid', 'product_carousel', 'product_grid', 'product_rows', 'spacer', 'divider', 'html'],
            ],
            'categories' => [
                'name' => 'Categories Landing',
                'type' => 'system',
                'url' => '/categories',
                'region' => true,
                'blocks' => ['promo_banner', 'notice', 'text', 'image', 'heading', 'cta', 'countdown', 'category_tiles', 'category_grid', 'product_carousel', 'product_grid', 'product_rows', 'spacer', 'divider', 'html'],
            ],
            'category' => [
                'name' => 'Category Page Template',
                'type' => 'system',
                'url' => null,
                'region' => true,
                'blocks' => ['promo_banner', 'notice', 'text', 'image', 'heading', 'cta', 'countdown', 'category_tiles', 'category_grid', 'product_carousel', 'product_grid', 'product_rows', 'spacer', 'divider', 'html'],
            ],
            'product' => [
                'name' => 'Product Page Template',
                'type' => 'system',
                'url' => null,
                'region' => true,
                'blocks' => ['notice', 'text', 'image', 'heading', 'cta', 'countdown', 'product_carousel', 'product_grid', 'product_rows', 'category_tiles', 'gallery', 'spacer', 'divider', 'html'],
            ],
            'cart' => [
                'name' => 'Cart Page',
                'type' => 'system',
                'url' => '/cart',
                'region' => true,
                'blocks' => ['notice', 'text', 'image'],
            ],
            'checkout' => [
                'name' => 'Checkout Page',
                'type' => 'system',
                'url' => '/checkout',
                'region' => true,
                'blocks' => ['notice', 'text'],
            ],
        ];
    }

    /**
     * Dynamic routes that have no editable content region of their own.
     *
     * @return array<int, array{name: string, url: ?string, type: string, note: string, admin_url: ?string}>
     */
    public static function virtualRoutes(): array
    {
        return [
            ['name' => 'Cart', 'url' => '/cart', 'type' => 'dynamic', 'note' => 'Cart logic is system-managed.', 'admin_url' => null],
            ['name' => 'Checkout', 'url' => '/checkout', 'type' => 'dynamic', 'note' => 'Checkout, validation and payments are protected.', 'admin_url' => null],
            ['name' => 'Thank you', 'url' => '/thank-you', 'type' => 'dynamic', 'note' => 'Post-purchase page.', 'admin_url' => null],
            ['name' => 'Support hub', 'url' => '/support', 'type' => 'dynamic', 'note' => 'Help centre / order tracking.', 'admin_url' => null],
            ['name' => 'Vendor storefront', 'url' => '/store/{slug}', 'type' => 'dynamic', 'note' => 'One page per vendor.', 'admin_url' => null],
            ['name' => 'Customer account', 'url' => '/customer/profile', 'type' => 'dynamic', 'note' => 'Authenticated account area.', 'admin_url' => null],
            ['name' => 'Customer orders', 'url' => '/customer/orders', 'type' => 'dynamic', 'note' => 'Authenticated order history.', 'admin_url' => null],
        ];
    }

    /**
     * Ensure the editable system pages exist as rows. Idempotent.
     */
    public function ensureSystemPages(): void
    {
        foreach (self::systemDefinitions() as $key => $def) {
            if ($key === 'home') {
                continue;
            }

            \App\Models\Page::firstOrCreate(
                ['system_key' => $key],
                [
                    'slug' => 'system-'.$key,
                    'status' => 1,
                    'page_type' => 'system',
                    'template' => 'default',
                    'is_system' => true,
                    'blocks' => [],
                ]
            );
        }
    }

    public function systemPage(string $key): ?\App\Models\Page
    {
        return \App\Models\Page::where('system_key', $key)->first();
    }
}
