<?php

namespace App\Services\Pages;

/**
 * Registry of selectable product-detail page templates.
 *
 * The chosen template is stored on the `product` system page (Page::template)
 * and drives which Blade template renders the storefront product page, so the
 * product page behaves like every other template-driven page in the builder.
 */
class ProductTemplates
{
    public const DEFAULT = 'marketplace';

    /**
     * @return array<string, array{label: string, view: string, description: string}>
     */
    public static function all(): array
    {
        return [
            'marketplace' => [
                'label' => 'Juvo Product Detail — Marketplace',
                'view' => 'themes.xylo.product-detail',
                'description' => 'Gallery, variant picker, seller panel, delivery + payments, reviews and similar products.',
            ],
            'classic' => [
                'label' => 'Classic Product Detail',
                'view' => 'themes.xylo.product-templates.classic',
                'description' => 'Compact single-column layout without the marketplace seller/delivery panels.',
            ],
        ];
    }

    /**
     * @return array<string, string> key => label
     */
    public static function options(): array
    {
        return collect(self::all())->map(fn ($t) => $t['label'])->all();
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function exists(?string $key): bool
    {
        return is_string($key) && array_key_exists($key, self::all());
    }

    /**
     * Resolve a template key to a valid one, falling back to the default.
     */
    public static function resolve(?string $key): string
    {
        return self::exists($key) ? $key : self::DEFAULT;
    }

    public static function view(?string $key): string
    {
        return self::all()[self::resolve($key)]['view'];
    }

    public static function label(?string $key): string
    {
        return self::all()[self::resolve($key)]['label'];
    }
}
