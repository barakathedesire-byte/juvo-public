<?php

namespace App\Services\Store;

use App\Services\Pages\PageBlocks;

/**
 * Vendor-safe subset of the page-builder component library.
 *
 * Vendors customise their own storefront home page. Marketplace-only blocks
 * (vendor recruitment, marketplace newsletter) and raw HTML are excluded so a
 * vendor can only compose components that render their own catalogue.
 */
class StoreBlocks
{
    public const TYPES = [
        'hero', 'promo_banner', 'product_carousel', 'product_grid', 'product_rows',
        'category_tiles', 'category_grid', 'heading', 'text', 'cta',
        'image', 'gallery', 'video', 'countdown', 'notice', 'spacer', 'divider',
    ];

    public static function all(): array
    {
        return PageBlocks::forRegion(self::TYPES);
    }

    public static function make(string $type): array
    {
        abort_unless(in_array($type, self::TYPES, true), 404);

        return PageBlocks::make($type);
    }

    public static function sanitize(array $blocks): array
    {
        return PageBlocks::sanitizeBlocks($blocks, self::TYPES);
    }
}
