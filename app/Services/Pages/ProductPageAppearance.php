<?php

namespace App\Services\Pages;

use App\Models\Page;

/**
 * Structured, whitelisted appearance settings for the product-detail page
 * template. Admins toggle which components render and pick a small set of
 * safe presentation options. No arbitrary CSS/JS is stored or emitted.
 */
class ProductPageAppearance
{
    /**
     * @return array<string, array{label: string, group: string}>
     */
    public const TOGGLES = [
        'show_breadcrumb' => ['label' => 'Breadcrumb', 'group' => 'Layout'],
        'show_share' => ['label' => 'Share buttons', 'group' => 'Layout'],
        'show_report' => ['label' => 'Report incorrect info link', 'group' => 'Layout'],
        'show_flash_panel' => ['label' => 'Flash sale / price-drops panel', 'group' => 'Merchandising'],
        'show_sponsored' => ['label' => 'Sponsored products panel', 'group' => 'Merchandising'],
        'show_similar' => ['label' => 'Similar products', 'group' => 'Related'],
        'show_reviews' => ['label' => 'Description / reviews / ask tabs', 'group' => 'Related'],
        'show_delivery_panel' => ['label' => 'Delivery & returns panel', 'group' => 'Sidebar'],
        'show_seller_card' => ['label' => 'Seller card', 'group' => 'Sidebar'],
        'show_sticky_cart' => ['label' => 'Sticky mobile add-to-cart bar', 'group' => 'Layout'],
    ];

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        $defaults = [];
        foreach (self::TOGGLES as $key => $meta) {
            $defaults[$key] = true;
        }

        return $defaults;
    }

    /** @return array<int, array{key: string, label: string, type: string, group: string, options?: array}> */
    public static function fields(): array
    {
        $fields = [];
        foreach (self::TOGGLES as $key => $meta) {
            $fields[] = ['key' => $key, 'label' => $meta['label'], 'type' => 'toggle', 'group' => $meta['group']];
        }

        return $fields;
    }

    /**
     * Sanitise submitted appearance settings against the whitelist.
     *
     * @param  mixed  $input
     * @return array<string, mixed>
     */
    public static function sanitize($input): array
    {
        $input = is_array($input) ? $input : [];
        $clean = [];

        foreach (self::TOGGLES as $key => $meta) {
            $clean[$key] = filter_var($input[$key] ?? true, FILTER_VALIDATE_BOOLEAN);
        }

        return $clean;
    }

    /**
     * Merged, defaults-applied settings for a page.
     *
     * @return array<string, mixed>
     */
    public static function forPage(?Page $page): array
    {
        $stored = $page && is_array($page->settings) ? $page->settings : [];

        return array_merge(self::defaults(), array_intersect_key($stored, self::defaults()));
    }
}
