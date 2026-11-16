<?php

namespace App\Services\Pages;

use Illuminate\Support\Str;

/**
 * The page-builder component library.
 *
 * Each block type declares its default payload and a field schema. The admin
 * builder renders settings forms from this schema, and the server uses the
 * same schema to whitelist and sanitise submitted block data, so unknown
 * fields, unsafe attributes and arbitrary markup never reach the database.
 */
class PageBlocks
{
    public static function all(): array
    {
        return self::definitions();
    }

    public static function has(string $type): bool
    {
        return array_key_exists($type, self::definitions());
    }

    public static function definition(string $type): ?array
    {
        return self::definitions()[$type] ?? null;
    }

    /**
     * Components allowed for a system content region (shop/cart/checkout/...).
     */
    public static function forRegion(?array $allowed): array
    {
        $defs = self::definitions();

        if ($allowed === null) {
            return $defs;
        }

        return array_intersect_key($defs, array_flip($allowed));
    }

    /**
     * A fresh block instance for a given type.
     */
    public static function make(string $type): array
    {
        $def = self::definition($type) ?? self::definitions()['text'];

        return [
            'id' => (string) Str::uuid(),
            'type' => $type,
            'hidden' => false,
            'settings' => $def['defaults'],
        ];
    }

    /**
     * Sanitise a full list of blocks: drop unknown types, coerce values to the
     * declared field schema, strip unsafe markup/attributes.
     */
    public static function sanitizeBlocks($blocks, ?array $allowedTypes = null): array
    {
        if (! is_array($blocks)) {
            return [];
        }

        $clean = [];

        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            $type = $block['type'] ?? null;

            if (! is_string($type) || ! self::has($type)) {
                continue;
            }

            if ($allowedTypes !== null && ! in_array($type, $allowedTypes, true)) {
                continue;
            }

            $def = self::definition($type);

            $clean[] = [
                'id' => self::safeId($block['id'] ?? null),
                'type' => $type,
                'hidden' => (bool) ($block['hidden'] ?? false),
                'settings' => self::sanitizeFields($block['settings'] ?? [], $def['fields']),
            ];
        }

        return $clean;
    }

    protected static function safeId($id): string
    {
        if (is_string($id) && preg_match('/^[A-Za-z0-9\-]{1,64}$/', $id)) {
            return $id;
        }

        return (string) Str::uuid();
    }

    protected static function sanitizeFields(array $values, array $fields): array
    {
        $out = [];

        foreach ($fields as $field) {
            $key = $field['key'];
            $type = $field['type'];
            $value = $values[$key] ?? ($field['default'] ?? null);

            $out[$key] = match ($type) {
                'repeater' => self::sanitizeRepeater($value, $field),
                'images' => self::sanitizeImages($value),
                'number' => self::sanitizeNumber($value, $field),
                'toggle' => (bool) filter_var($value, FILTER_VALIDATE_BOOLEAN),
                'select' => self::sanitizeSelect($value, $field),
                'color' => self::sanitizeColor($value),
                'richtext', 'html' => self::sanitizeHtml((string) $value),
                'url' => self::sanitizeUrl((string) $value),
                'image' => self::sanitizeImagePath($value),
                'datetime' => self::sanitizeDate($value),
                default => self::sanitizeText($value, $field),
            };
        }

        return $out;
    }

    protected static function sanitizeRepeater($value, array $field): array
    {
        if (! is_array($value)) {
            return [];
        }

        $rows = [];

        foreach ($value as $row) {
            if (! is_array($row)) {
                continue;
            }

            $clean = [];

            foreach ($field['fields'] as $sub) {
                $subValue = $row[$sub['key']] ?? ($sub['default'] ?? null);
                $clean[$sub['key']] = match ($sub['type']) {
                    'number' => self::sanitizeNumber($subValue, $sub),
                    'toggle' => (bool) filter_var($subValue, FILTER_VALIDATE_BOOLEAN),
                    'select' => self::sanitizeSelect($subValue, $sub),
                    'color' => self::sanitizeColor($subValue),
                    'image' => self::sanitizeImagePath($subValue),
                    'category', 'collection' => self::sanitizeText($subValue, ['max' => 20]),
                    'richtext', 'html' => self::sanitizeHtml((string) $subValue),
                    'url' => self::sanitizeUrl((string) $subValue),
                    'datetime' => self::sanitizeDate($subValue),
                    default => self::sanitizeText($subValue, $sub),
                };
            }

            $rows[] = $clean;
        }

        return $rows;
    }

    protected static function sanitizeImages($value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];

        foreach ($value as $img) {
            $path = self::sanitizeImagePath($img);
            if ($path) {
                $out[] = $path;
            }
        }

        return $out;
    }

    protected static function sanitizeNumber($value, array $field)
    {
        if ($value === null || $value === '') {
            return $field['default'] ?? 0;
        }

        $n = is_numeric($value) ? $value + 0 : 0;

        if (isset($field['min']) && $n < $field['min']) {
            $n = $field['min'];
        }
        if (isset($field['max']) && $n > $field['max']) {
            $n = $field['max'];
        }

        return $n;
    }

    protected static function sanitizeSelect($value, array $field)
    {
        $options = array_keys($field['options'] ?? []);

        return in_array($value, $options, true) ? $value : ($field['default'] ?? ($options[0] ?? null));
    }

    protected static function sanitizeColor($value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        return preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', trim($value)) ? trim($value) : null;
    }

    protected static function sanitizeText($value, array $field): string
    {
        $text = is_scalar($value) ? (string) $value : '';
        $max = $field['max'] ?? 500;

        return Str::limit(trim(strip_tags($text)), $max, '');
    }

    protected static function sanitizeImagePath($value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $value = trim($value);

        if (preg_match('#^https?://#i', $value)) {
            return $value;
        }

        // Internal storage path only (no traversal, no scripts).
        $value = ltrim($value, '/');

        return preg_match('#^[A-Za-z0-9_\-./]+$#', $value) && ! str_contains($value, '..') ? $value : null;
    }

    protected static function sanitizeUrl(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (preg_match('#^\s*(javascript|data|vbscript):#i', $value)) {
            return null;
        }

        return $value;
    }

    protected static function sanitizeDate($value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->toDateTimeString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Strip script/style/iframe/object/embed tags, inline event handlers and
     * unsafe URL schemes from author-supplied HTML.
     */
    public static function sanitizeHtml(string $html): string
    {
        return \App\Support\Html\HtmlSanitizer::clean($html);
    }

    protected static function definitions(): array
    {
        $headingField = ['key' => 'heading', 'type' => 'text', 'label' => 'Heading', 'max' => 160];
        $subtitleField = ['key' => 'subtitle', 'type' => 'textarea', 'label' => 'Subtitle', 'max' => 300];
        $alignField = ['key' => 'align', 'type' => 'select', 'label' => 'Alignment', 'default' => 'left', 'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right']];
        $bgColorField = ['key' => 'bg_color', 'type' => 'color', 'label' => 'Background colour', 'default' => null];
        $textColorField = ['key' => 'text_color', 'type' => 'color', 'label' => 'Text colour', 'default' => null];

        $productSource = ['key' => 'source', 'type' => 'select', 'label' => 'Product source', 'default' => 'featured', 'options' => [
            'featured' => 'Featured products',
            'sponsored' => 'Sponsored products',
            'bestsellers' => 'Best sellers',
            'new_arrivals' => 'New arrivals',
            'daily_deals' => 'Daily deals',
            'trending' => 'Trending',
            'category' => 'By category',
            'collection' => 'By collection',
        ]];
        $categoryPicker = ['key' => 'category_id', 'type' => 'category', 'label' => 'Category', 'default' => null];
        $collectionPicker = ['key' => 'collection_id', 'type' => 'collection', 'label' => 'Collection', 'default' => null];

        $rowSourceOptions = [
            'manual' => 'Manual selection',
            'featured' => 'Featured products',
            'sponsored' => 'Sponsored products',
            'flash_sale' => 'Flash sale / discounted',
            'bestsellers' => 'Best sellers',
            'new_arrivals' => 'New arrivals',
            'trending' => 'Trending',
            'daily_deals' => 'Daily deals',
            'category' => 'By category',
            'collection' => 'By collection',
            'vendor' => 'By vendor',
            'recommended' => 'Recommended for shopper',
            'recently_viewed' => 'Recently viewed',
        ];

        $rowSortOptions = [
            'default' => 'Default',
            'newest' => 'Newest first',
            'price_asc' => 'Price: low to high',
            'price_desc' => 'Price: high to low',
            'popular' => 'Most popular',
            'discount' => 'Biggest discount',
        ];

        return [
            'hero' => [
                'label' => 'Hero banner / carousel',
                'icon' => 'fa-images',
                'group' => 'Media',
                'defaults' => ['autoplay' => true, 'speed' => 5000, 'arrows' => true, 'dots' => true, 'height' => 'medium', 'items' => []],
                'fields' => [
                    ['key' => 'items', 'type' => 'repeater', 'label' => 'Slides', 'item_label' => 'Slide', 'fields' => [
                        ['key' => 'image', 'type' => 'image', 'label' => 'Image (desktop)'],
                        ['key' => 'mobile_image', 'type' => 'image', 'label' => 'Image (mobile)'],
                        ['key' => 'badge', 'type' => 'text', 'label' => 'Badge', 'max' => 40],
                        ['key' => 'title', 'type' => 'text', 'label' => 'Heading', 'max' => 160],
                        ['key' => 'subtitle', 'type' => 'text', 'label' => 'Subtitle', 'max' => 200],
                        ['key' => 'description', 'type' => 'textarea', 'label' => 'Description', 'max' => 300],
                        ['key' => 'cta_label', 'type' => 'text', 'label' => 'Button label', 'max' => 40],
                        ['key' => 'cta_url', 'type' => 'url', 'label' => 'Button link'],
                        ['key' => 'text_align', 'type' => 'select', 'label' => 'Text alignment', 'default' => 'left', 'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right']],
                    ]],
                    ['key' => 'autoplay', 'type' => 'toggle', 'label' => 'Autoplay', 'default' => true],
                    ['key' => 'speed', 'type' => 'number', 'label' => 'Autoplay speed (ms)', 'default' => 5000, 'min' => 2000, 'max' => 15000],
                    ['key' => 'arrows', 'type' => 'toggle', 'label' => 'Navigation arrows', 'default' => true],
                    ['key' => 'dots', 'type' => 'toggle', 'label' => 'Pagination dots', 'default' => true],
                    ['key' => 'height', 'type' => 'select', 'label' => 'Height', 'default' => 'medium', 'options' => ['auto' => 'Auto', 'medium' => 'Medium', 'tall' => 'Tall']],
                ],
            ],
            'promo_banner' => [
                'label' => 'Promotional banner',
                'icon' => 'fa-flag',
                'group' => 'Media',
                'defaults' => ['image' => null, 'heading' => 'Big savings this week', 'subtitle' => null, 'cta_label' => 'Shop now', 'cta_url' => '/products', 'align' => 'left', 'overlay' => 'dark', 'bg_color' => null, 'text_color' => null],
                'fields' => [
                    ['key' => 'image', 'type' => 'image', 'label' => 'Image'],
                    ['key' => 'heading', 'type' => 'text', 'label' => 'Heading', 'max' => 160],
                    ['key' => 'subtitle', 'type' => 'textarea', 'label' => 'Subtitle', 'max' => 300],
                    ['key' => 'cta_label', 'type' => 'text', 'label' => 'Button label', 'max' => 40],
                    ['key' => 'cta_url', 'type' => 'url', 'label' => 'Button link'],
                    $alignField,
                    ['key' => 'overlay', 'type' => 'select', 'label' => 'Image overlay', 'default' => 'dark', 'options' => ['none' => 'None', 'light' => 'Light', 'dark' => 'Dark']],
                    $bgColorField,
                    $textColorField,
                ],
            ],
            'category_tiles' => [
                'label' => 'Circular category tiles',
                'icon' => 'fa-circle-nodes',
                'group' => 'Catalogue',
                'defaults' => ['title' => 'Shop by category', 'subtitle' => null, 'source' => 'manual', 'columns' => 6, 'items' => []],
                'fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title', 'max' => 120],
                    $subtitleField,
                    ['key' => 'source', 'type' => 'select', 'label' => 'Source', 'default' => 'manual', 'options' => ['manual' => 'Manual selection', 'top' => 'Top categories (auto)']],
                    ['key' => 'columns', 'type' => 'select', 'label' => 'Columns', 'default' => '6', 'options' => ['3' => '3', '4' => '4', '5' => '5', '6' => '6', '8' => '8']],
                    ['key' => 'items', 'type' => 'repeater', 'label' => 'Tiles', 'item_label' => 'Tile', 'fields' => [
                        ['key' => 'title', 'type' => 'text', 'label' => 'Title', 'max' => 80],
                        ['key' => 'image', 'type' => 'image', 'label' => 'Image'],
                        ['key' => 'link_type', 'type' => 'select', 'label' => 'Link type', 'default' => 'category', 'options' => ['category' => 'Category', 'collection' => 'Collection', 'url' => 'URL']],
                        ['key' => 'link_value', 'type' => 'text', 'label' => 'Link value', 'max' => 200],
                    ]],
                ],
            ],
            'category_grid' => [
                'label' => 'Category grid',
                'icon' => 'fa-table-cells',
                'group' => 'Catalogue',
                'defaults' => ['title' => 'Browse categories', 'subtitle' => null, 'source' => 'top', 'limit' => 8, 'columns' => 4, 'show_count' => true, 'items' => []],
                'fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title', 'max' => 120],
                    $subtitleField,
                    ['key' => 'source', 'type' => 'select', 'label' => 'Source', 'default' => 'top', 'options' => ['top' => 'Top categories (auto)', 'manual' => 'Manual selection']],
                    ['key' => 'limit', 'type' => 'number', 'label' => 'Max categories', 'default' => 8, 'min' => 1, 'max' => 24],
                    ['key' => 'columns', 'type' => 'select', 'label' => 'Columns', 'default' => '4', 'options' => ['2' => '2', '3' => '3', '4' => '4', '6' => '6']],
                    ['key' => 'show_count', 'type' => 'toggle', 'label' => 'Show product count', 'default' => true],
                    ['key' => 'items', 'type' => 'repeater', 'label' => 'Categories', 'item_label' => 'Category', 'fields' => [
                        ['key' => 'link_value', 'type' => 'text', 'label' => 'Category slug', 'max' => 120],
                    ]],
                ],
            ],
            'product_carousel' => [
                'label' => 'Product carousel',
                'icon' => 'fa-arrows-left-right',
                'group' => 'Catalogue',
                'defaults' => ['title' => 'Featured products', 'subtitle' => null, 'source' => 'featured', 'category_id' => null, 'collection_id' => null, 'limit' => 10, 'autoplay' => false, 'arrows' => true, 'dots' => true, 'show_price' => true, 'bg_color' => null, 'text_color' => null],
                'fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title', 'max' => 120],
                    $subtitleField,
                    $productSource,
                    $categoryPicker,
                    $collectionPicker,
                    ['key' => 'limit', 'type' => 'number', 'label' => 'Max products', 'default' => 10, 'min' => 1, 'max' => 24],
                    ['key' => 'autoplay', 'type' => 'toggle', 'label' => 'Autoplay', 'default' => false],
                    ['key' => 'arrows', 'type' => 'toggle', 'label' => 'Arrows', 'default' => true],
                    ['key' => 'dots', 'type' => 'toggle', 'label' => 'Dots', 'default' => true],
                    ['key' => 'show_price', 'type' => 'toggle', 'label' => 'Show price', 'default' => true],
                    $bgColorField,
                    $textColorField,
                ],
            ],
            'product_grid' => [
                'label' => 'Product grid',
                'icon' => 'fa-grip',
                'group' => 'Catalogue',
                'defaults' => ['title' => 'Products', 'subtitle' => null, 'source' => 'new_arrivals', 'category_id' => null, 'collection_id' => null, 'limit' => 8, 'columns' => 4, 'show_price' => true, 'bg_color' => null, 'text_color' => null],
                'fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title', 'max' => 120],
                    $subtitleField,
                    $productSource,
                    $categoryPicker,
                    $collectionPicker,
                    ['key' => 'limit', 'type' => 'number', 'label' => 'Max products', 'default' => 8, 'min' => 1, 'max' => 24],
                    ['key' => 'columns', 'type' => 'select', 'label' => 'Columns', 'default' => '4', 'options' => ['2' => '2', '3' => '3', '4' => '4', '5' => '5']],
                    ['key' => 'show_price', 'type' => 'toggle', 'label' => 'Show price', 'default' => true],
                    $bgColorField,
                    $textColorField,
                ],
            ],
            'product_rows' => [
                'label' => 'Product section (multi-row)',
                'icon' => 'fa-table-rows',
                'group' => 'Catalogue',
                'defaults' => ['title' => null, 'subtitle' => null, 'bg_color' => null, 'text_color' => null, 'rows' => []],
                'fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Section title', 'max' => 120],
                    $subtitleField,
                    $bgColorField,
                    $textColorField,
                    ['key' => 'rows', 'type' => 'repeater', 'label' => 'Rows', 'item_label' => 'Row', 'fields' => [
                        ['key' => 'title', 'type' => 'text', 'label' => 'Row title', 'max' => 120],
                        ['key' => 'source', 'type' => 'select', 'label' => 'Product source', 'default' => 'featured', 'options' => $rowSourceOptions],
                        ['key' => 'category_id', 'type' => 'category', 'label' => 'Category', 'default' => null],
                        ['key' => 'collection_id', 'type' => 'collection', 'label' => 'Collection', 'default' => null],
                        ['key' => 'vendor_id', 'type' => 'number', 'label' => 'Vendor ID (vendor source)', 'default' => null, 'min' => 0, 'max' => 999999],
                        ['key' => 'product_ids', 'type' => 'text', 'label' => 'Manual product IDs (comma separated)', 'max' => 500],
                        ['key' => 'limit', 'type' => 'number', 'label' => 'Max products', 'default' => 10, 'min' => 1, 'max' => 24],
                        ['key' => 'sort', 'type' => 'select', 'label' => 'Sort order', 'default' => 'default', 'options' => $rowSortOptions],
                        ['key' => 'layout', 'type' => 'select', 'label' => 'Layout', 'default' => 'carousel', 'options' => ['carousel' => 'Horizontal carousel', 'grid' => 'Grid']],
                        ['key' => 'columns_desktop', 'type' => 'number', 'label' => 'Cards visible (desktop)', 'default' => 5, 'min' => 2, 'max' => 8],
                        ['key' => 'columns_tablet', 'type' => 'number', 'label' => 'Cards visible (tablet)', 'default' => 3, 'min' => 1, 'max' => 6],
                        ['key' => 'columns_mobile', 'type' => 'number', 'label' => 'Cards visible (mobile)', 'default' => 2, 'min' => 1, 'max' => 4],
                        ['key' => 'arrows', 'type' => 'toggle', 'label' => 'Show navigation arrows', 'default' => true],
                        ['key' => 'see_all', 'type' => 'toggle', 'label' => 'Show "See all" link', 'default' => true],
                        ['key' => 'see_all_url', 'type' => 'url', 'label' => '"See all" link', 'default' => null],
                        ['key' => 'visible', 'type' => 'toggle', 'label' => 'Visible on the site', 'default' => true],
                        ['key' => 'empty_state', 'type' => 'text', 'label' => 'Empty-state message', 'max' => 160],
                    ]],
                ],
            ],
            'heading' => [
                'label' => 'Heading',
                'icon' => 'fa-heading',
                'group' => 'Text',
                'defaults' => ['text' => 'Section heading', 'level' => 'h2', 'align' => 'left', 'color' => null],
                'fields' => [
                    ['key' => 'text', 'type' => 'text', 'label' => 'Text', 'max' => 200],
                    ['key' => 'level', 'type' => 'select', 'label' => 'Level', 'default' => 'h2', 'options' => ['h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4']],
                    $alignField,
                    ['key' => 'color', 'type' => 'color', 'label' => 'Colour', 'default' => null],
                ],
            ],
            'text' => [
                'label' => 'Rich text',
                'icon' => 'fa-align-left',
                'group' => 'Text',
                'defaults' => ['heading' => null, 'body' => '<p>Write something…</p>', 'align' => 'left', 'max_width' => 'md'],
                'fields' => [
                    ['key' => 'heading', 'type' => 'text', 'label' => 'Heading', 'max' => 160],
                    ['key' => 'body', 'type' => 'richtext', 'label' => 'Body'],
                    $alignField,
                    ['key' => 'max_width', 'type' => 'select', 'label' => 'Width', 'default' => 'md', 'options' => ['sm' => 'Narrow', 'md' => 'Medium', 'lg' => 'Wide', 'full' => 'Full']],
                ],
            ],
            'cta' => [
                'label' => 'Call to action',
                'icon' => 'fa-bullhorn',
                'group' => 'Text',
                'defaults' => ['heading' => 'Ready to shop?', 'subtitle' => null, 'button_label' => 'Explore', 'button_url' => '/products', 'bg_color' => '#f68b1e', 'text_color' => '#ffffff', 'align' => 'center'],
                'fields' => [
                    $headingField,
                    $subtitleField,
                    ['key' => 'button_label', 'type' => 'text', 'label' => 'Button label', 'max' => 40],
                    ['key' => 'button_url', 'type' => 'url', 'label' => 'Button link'],
                    $bgColorField,
                    $textColorField,
                    $alignField,
                ],
            ],
            'image' => [
                'label' => 'Image',
                'icon' => 'fa-image',
                'group' => 'Media',
                'defaults' => ['image' => null, 'mobile_image' => null, 'link' => null, 'caption' => null, 'alt' => null, 'align' => 'center', 'rounded' => true],
                'fields' => [
                    ['key' => 'image', 'type' => 'image', 'label' => 'Image'],
                    ['key' => 'mobile_image', 'type' => 'image', 'label' => 'Image (mobile)'],
                    ['key' => 'link', 'type' => 'url', 'label' => 'Link'],
                    ['key' => 'caption', 'type' => 'text', 'label' => 'Caption', 'max' => 160],
                    ['key' => 'alt', 'type' => 'text', 'label' => 'Alt text', 'max' => 160],
                    $alignField,
                    ['key' => 'rounded', 'type' => 'toggle', 'label' => 'Rounded corners', 'default' => true],
                ],
            ],
            'gallery' => [
                'label' => 'Image gallery',
                'icon' => 'fa-images',
                'group' => 'Media',
                'defaults' => ['title' => null, 'columns' => 3, 'images' => []],
                'fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title', 'max' => 120],
                    ['key' => 'columns', 'type' => 'select', 'label' => 'Columns', 'default' => '3', 'options' => ['2' => '2', '3' => '3', '4' => '4']],
                    ['key' => 'images', 'type' => 'images', 'label' => 'Images'],
                ],
            ],
            'video' => [
                'label' => 'Video',
                'icon' => 'fa-video',
                'group' => 'Media',
                'defaults' => ['title' => null, 'url' => null, 'poster' => null, 'ratio' => '16:9'],
                'fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title', 'max' => 120],
                    ['key' => 'url', 'type' => 'url', 'label' => 'Video URL (YouTube/Vimeo/mp4)'],
                    ['key' => 'poster', 'type' => 'image', 'label' => 'Poster image'],
                    ['key' => 'ratio', 'type' => 'select', 'label' => 'Aspect ratio', 'default' => '16:9', 'options' => ['16:9' => '16:9', '4:3' => '4:3', '1:1' => '1:1']],
                ],
            ],
            'countdown' => [
                'label' => 'Countdown / deal',
                'icon' => 'fa-clock',
                'group' => 'Promotion',
                'defaults' => ['heading' => 'Deal ends soon', 'subtitle' => null, 'ends_at' => null, 'cta_label' => 'Shop the deal', 'cta_url' => '/products', 'bg_color' => '#111827', 'text_color' => '#ffffff'],
                'fields' => [
                    $headingField,
                    $subtitleField,
                    ['key' => 'ends_at', 'type' => 'datetime', 'label' => 'Ends at'],
                    ['key' => 'cta_label', 'type' => 'text', 'label' => 'Button label', 'max' => 40],
                    ['key' => 'cta_url', 'type' => 'url', 'label' => 'Button link'],
                    $bgColorField,
                    $textColorField,
                ],
            ],
            'newsletter' => [
                'label' => 'Newsletter signup',
                'icon' => 'fa-envelope',
                'group' => 'Promotion',
                'defaults' => ['heading' => 'Get the best deals', 'subtitle' => 'Subscribe for offers and new arrivals.', 'button_label' => 'Subscribe', 'placeholder' => 'Your email address'],
                'fields' => [
                    $headingField,
                    $subtitleField,
                    ['key' => 'button_label', 'type' => 'text', 'label' => 'Button label', 'max' => 40],
                    ['key' => 'placeholder', 'type' => 'text', 'label' => 'Input placeholder', 'max' => 60],
                ],
            ],
            'vendor_recruit' => [
                'label' => 'Vendor recruitment',
                'icon' => 'fa-store',
                'group' => 'Promotion',
                'defaults' => ['heading' => 'Sell on Juvo', 'subtitle' => 'Open your store and reach more customers.', 'cta_label' => 'Become a vendor', 'cta_url' => '/vendor/register', 'image' => null],
                'fields' => [
                    $headingField,
                    $subtitleField,
                    ['key' => 'cta_label', 'type' => 'text', 'label' => 'Button label', 'max' => 40],
                    ['key' => 'cta_url', 'type' => 'url', 'label' => 'Button link'],
                    ['key' => 'image', 'type' => 'image', 'label' => 'Image'],
                ],
            ],
            'notice' => [
                'label' => 'Notice / info message',
                'icon' => 'fa-circle-info',
                'group' => 'Text',
                'defaults' => ['text' => 'Free delivery on eligible orders.', 'tone' => 'info'],
                'fields' => [
                    ['key' => 'text', 'type' => 'textarea', 'label' => 'Message', 'max' => 300],
                    ['key' => 'tone', 'type' => 'select', 'label' => 'Tone', 'default' => 'info', 'options' => ['info' => 'Info', 'success' => 'Success', 'warning' => 'Warning']],
                ],
            ],
            'section' => [
                'label' => 'Content section',
                'icon' => 'fa-layer-group',
                'group' => 'Layout',
                'defaults' => ['heading' => 'Section heading', 'subtitle' => null, 'body' => '<p>Write your section content…</p>', 'image' => null, 'image_position' => 'right', 'bg_color' => null, 'text_color' => null, 'padding' => 32, 'align' => 'left', 'max_width' => 'lg'],
                'fields' => [
                    $headingField,
                    $subtitleField,
                    ['key' => 'body', 'type' => 'richtext', 'label' => 'Body'],
                    ['key' => 'image', 'type' => 'image', 'label' => 'Image'],
                    ['key' => 'image_position', 'type' => 'select', 'label' => 'Image position', 'default' => 'right', 'options' => ['right' => 'Right', 'left' => 'Left', 'top' => 'Top', 'bottom' => 'Bottom']],
                    $bgColorField,
                    $textColorField,
                    ['key' => 'padding', 'type' => 'number', 'label' => 'Padding (px)', 'default' => 32, 'min' => 0, 'max' => 120],
                    $alignField,
                    ['key' => 'max_width', 'type' => 'select', 'label' => 'Width', 'default' => 'lg', 'options' => ['sm' => 'Narrow', 'md' => 'Medium', 'lg' => 'Wide', 'full' => 'Full']],
                ],
            ],
            'shape' => [
                'label' => 'Decorative shape',
                'icon' => 'fa-shapes',
                'group' => 'Layout',
                'defaults' => ['shape' => 'circle', 'color' => '#f68b1e', 'width' => 120, 'height' => 120, 'align' => 'center', 'opacity' => 15, 'bg_color' => null],
                'fields' => [
                    ['key' => 'shape', 'type' => 'select', 'label' => 'Shape', 'default' => 'circle', 'options' => ['circle' => 'Circle', 'rounded' => 'Rounded rectangle', 'line' => 'Line', 'blob' => 'Blob']],
                    ['key' => 'color', 'type' => 'color', 'label' => 'Colour', 'default' => '#f68b1e'],
                    ['key' => 'width', 'type' => 'number', 'label' => 'Width (px)', 'default' => 120, 'min' => 8, 'max' => 600],
                    ['key' => 'height', 'type' => 'number', 'label' => 'Height (px)', 'default' => 120, 'min' => 2, 'max' => 600],
                    $alignField,
                    ['key' => 'opacity', 'type' => 'number', 'label' => 'Opacity (%)', 'default' => 15, 'min' => 0, 'max' => 100],
                    $bgColorField,
                ],
            ],
            'spacer' => [
                'label' => 'Spacer',
                'icon' => 'fa-arrows-up-down',
                'group' => 'Layout',
                'defaults' => ['height' => 40, 'bg_color' => null],
                'fields' => [
                    ['key' => 'height', 'type' => 'number', 'label' => 'Height (px)', 'default' => 40, 'min' => 8, 'max' => 240],
                    $bgColorField,
                ],
            ],
            'divider' => [
                'label' => 'Divider',
                'icon' => 'fa-minus',
                'group' => 'Layout',
                'defaults' => ['style' => 'solid', 'color' => null],
                'fields' => [
                    ['key' => 'style', 'type' => 'select', 'label' => 'Style', 'default' => 'solid', 'options' => ['solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted']],
                    ['key' => 'color', 'type' => 'color', 'label' => 'Colour', 'default' => null],
                ],
            ],
            'html' => [
                'label' => 'Custom HTML',
                'icon' => 'fa-code',
                'group' => 'Advanced',
                'defaults' => ['html' => ''],
                'fields' => [
                    ['key' => 'html', 'type' => 'html', 'label' => 'HTML (scripts are stripped)'],
                ],
            ],
        ];
    }
}
