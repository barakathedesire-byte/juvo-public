<?php

namespace App\Support\Rider;

use App\Models\HomepageSetting;

class RiderBuilderContent
{
    public static function defaults(): array
    {
        return [
            'brand_name' => 'Juvo Rider',
            'brand_tagline' => 'Delivery partner',
            'brand_icon' => 'fas fa-motorcycle',
            'app_title' => 'Juvo Rider',
            'primary_color' => '#ff850f',
            'primary_dark' => '#e67b0d',
            'primary_light' => '#ffe8d6',
            'canvas_color' => '#f5f7fa',
            'success_color' => '#118a5b',
            'danger_color' => '#d43f4b',
            'support_phone' => (string) (getWebConfig('phone_number') ?: getWebConfig('contact_phone') ?: ''),
            'support_email' => (string) (config('mail.from.address') ?: ''),
            'hero_greeting_suffix' => 'Ready to make deliveries today?',
            'dashboard_earnings_label' => "Today's earnings",
            'empty_assignment_copy' => "You're all caught up. New assignments will appear here while you are online.",
            'features' => [
                'show_earnings' => true,
                'show_orders' => true,
                'show_profile' => true,
                'show_notifications' => true,
                'show_availability' => true,
                'show_cod' => true,
                'show_map' => true,
            ],
            'navigation' => [
                ['key' => 'dashboard', 'label' => 'Home', 'icon' => 'fas fa-house', 'enabled' => true],
                ['key' => 'orders', 'label' => 'Deliveries', 'icon' => 'fas fa-box', 'enabled' => true],
                ['key' => 'earnings', 'label' => 'Earnings', 'icon' => 'fas fa-wallet', 'enabled' => true],
                ['key' => 'profile', 'label' => 'Profile', 'icon' => 'far fa-user', 'enabled' => true],
            ],
        ];
    }

    public static function settings(): array
    {
        $defaults = self::defaults();
        $saved = HomepageSetting::json('rider_builder', []);

        if (! is_array($saved)) {
            $saved = [];
        }

        $settings = array_replace_recursive($defaults, $saved);

        foreach (['primary_color', 'primary_dark', 'primary_light', 'canvas_color', 'success_color', 'danger_color'] as $color) {
            if (! self::validColor($settings[$color] ?? null)) {
                $settings[$color] = $defaults[$color];
            }
        }

        $settings['navigation'] = self::normalizeNavigation($settings['navigation'] ?? [], $defaults['navigation']);
        $settings['features'] = array_merge($defaults['features'], is_array($settings['features'] ?? null) ? $settings['features'] : []);

        return $settings;
    }

    public static function forDisplay(): array
    {
        return self::settings();
    }

    protected static function validColor(?string $value): bool
    {
        return is_string($value) && (bool) preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value);
    }

    protected static function normalizeNavigation($input, array $defaults): array
    {
        if (! is_array($input) || $input === []) {
            return $defaults;
        }

        $allowed = ['dashboard', 'orders', 'earnings', 'profile'];
        $normalized = [];

        foreach ($input as $item) {
            if (! is_array($item)) {
                continue;
            }

            $key = (string) ($item['key'] ?? '');
            if (! in_array($key, $allowed, true)) {
                continue;
            }

            $normalized[] = [
                'key' => $key,
                'label' => trim((string) ($item['label'] ?? '')) ?: self::defaultLabel($key),
                'icon' => trim((string) ($item['icon'] ?? '')) ?: self::defaultIcon($key),
                'enabled' => (bool) ($item['enabled'] ?? true),
            ];
        }

        if ($normalized === []) {
            return $defaults;
        }

        return $normalized;
    }

    protected static function defaultLabel(string $key): string
    {
        return match ($key) {
            'orders' => 'Deliveries',
            'earnings' => 'Earnings',
            'profile' => 'Profile',
            default => 'Home',
        };
    }

    protected static function defaultIcon(string $key): string
    {
        return match ($key) {
            'orders' => 'fas fa-box',
            'earnings' => 'fas fa-wallet',
            'profile' => 'far fa-user',
            default => 'fas fa-house',
        };
    }
}
