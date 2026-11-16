<?php

namespace App\Services\Admin;

use App\Models\AdminPortalTab;
use App\Models\AdminSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Reads/writes admin-portal self-configuration (appearance + custom tabs).
 *
 * This only governs presentation of the staff portal. It never grants access:
 * every tab and route is still enforced by permission middleware, and custom
 * tabs are hidden from staff who lack the tab's declared permission.
 */
class AdminSettingsService
{
    public const CACHE_KEY = 'admin.portal.settings';

    /**
     * Appearance defaults. Keys are stable and safe to reference in Blade.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'primary_color' => '#f68b1e',
            'accent_color' => '#d9760c',
            'sidebar_bg' => '#1c2230',
            'sidebar_bg_end' => '#262d3d',
            'sidebar_text' => '#c7cdda',
            'content_bg' => '',
            'border_radius' => '22px',
            'font_family' => 'Nunito',
            'animations' => true,
            'compact_sidebar' => false,
            'brand_name' => 'Juvo Admin',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if (! $this->tableReady()) {
            return static::defaults();
        }

        $stored = Cache::rememberForever(self::CACHE_KEY, function () {
            return AdminSetting::query()->pluck('value', 'key')->all();
        });

        return array_merge(static::defaults(), $stored);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function put(array $values): void
    {
        if (! $this->tableReady()) {
            return;
        }

        foreach ($values as $key => $value) {
            AdminSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Custom tabs a given staff member may see, ordered.
     *
     * @return array<int, AdminPortalTab>
     */
    public function visibleCustomTabs(?\App\Models\User $user = null): array
    {
        if (! $this->tableReady()) {
            return [];
        }

        return AdminPortalTab::query()
            ->enabled()
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->filter(function (AdminPortalTab $tab) use ($user) {
                if ($tab->permission === null || $tab->permission === '') {
                    return true;
                }

                return $user !== null && $user->can($tab->permission);
            })
            ->values()
            ->all();
    }

    /**
     * Inline CSS that overrides the design-system's :root tokens so the chosen
     * colours, radius and feel apply across the whole staff panel.
     */
    public function cssVariables(): string
    {
        $s = $this->all();

        $primary = (string) ($s['primary_color'] ?: '#f68b1e');
        $accent = (string) ($s['accent_color'] ?: '#d9760c');
        $side1 = (string) ($s['sidebar_bg'] ?: '#1c2230');
        $side2 = (string) ($s['sidebar_bg_end'] ?: '#262d3d');
        $sideText = (string) ($s['sidebar_text'] ?: '#c7cdda');
        $radius = (string) ($s['border_radius'] ?: '22px');

        $vars = [
            // panel-bubble design tokens (real colours used everywhere)
            '--pb-accent' => $primary,
            '--pb-accent-deep' => $accent,
            '--pb-accent-2' => self::lighten($primary, 0.22),
            '--pb-accent-soft' => self::toRgba($primary, 0.12),
            '--pb-accent-ring' => self::toRgba($primary, 0.32),
            '--pb-shadow-accent' => self::toRgba($primary, 0.32),
            '--pb-side-1' => $side1,
            '--pb-side-2' => $side2,
            '--pb-side-text' => $sideText,
            '--pb-radius' => $radius,

            // admin-juvo tokens
            '--juvo-primary' => $primary,
            '--juvo-accent' => $accent,
            '--juvo-sidebar-text' => $sideText,
            '--juvo-radius' => '0.5rem',
        ];

        if (! empty($s['content_bg'])) {
            $vars['--pb-bg'] = (string) $s['content_bg'];
            $vars['--juvo-content-bg'] = (string) $s['content_bg'];
        }

        $css = ':root{';
        foreach ($vars as $name => $value) {
            $css .= $name.':'.self::sanitizeColor($value).';';
        }
        $css .= '}';

        if (! empty($s['compact_sidebar'])) {
            $css .= '#sidebar{width:78px}.logo-container,#sidebar .search-container{padding-left:4px;padding-right:4px}'
                .'#sidebar .nav-link{justify-content:center;padding-left:.4rem;padding-right:.4rem}'
                .'#sidebar .nav-link span:not(.badge){display:none}'
                .'#sidebar .nav-section-header{font-size:.55rem;text-align:center;letter-spacing:0}';
        }

        return $css;
    }

    /** Strip anything that could break out of a CSS declaration. */
    protected static function sanitizeColor(string $value): string
    {
        return preg_replace('/[^#0-9a-zA-Z%(),.\s-]/', '', $value) ?? '';
    }

    protected static function toRgba(string $hex, float $alpha): string
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (strlen($hex) !== 6) {
            return 'rgba(246,139,30,'.$alpha.')';
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return sprintf('rgba(%d,%d,%d,%s)', $r, $g, $b, $alpha);
    }

    protected static function lighten(string $hex, float $amount): string
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (strlen($hex) !== 6) {
            return $hex;
        }

        $out = '';
        foreach (str_split($hex, 2) as $pair) {
            $v = hexdec($pair) + (int) round((255 - hexdec($pair)) * $amount);
            $out .= sprintf('%02x', min(255, max(0, $v)));
        }

        return '#'.$out;
    }

    protected function tableReady(): bool
    {
        return Schema::hasTable('admin_settings');
    }
}
