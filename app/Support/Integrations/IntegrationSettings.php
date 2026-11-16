<?php

namespace App\Support\Integrations;

use App\Models\StoreSetting;

/**
 * Read access to the integration settings an administrator stores from the
 * admin "Integrations" screen.
 *
 * An integration is stored as three store settings:
 *   integration_enabled_<key>   "1" / "0"
 *   integration_name_<key>      display name
 *   integration_config_<key>    JSON object with the credentials
 *
 * The values are cached per request because the storefront reads them on every
 * checkout render.
 */
class IntegrationSettings
{
    /** @var array<string, array<string, string>>|null */
    protected static ?array $configs = null;

    /** @var array<string, string>|null */
    protected static ?array $names = null;

    /** @var array<string, string>|null */
    protected static ?array $enabled = null;

    /**
     * @return array<string, string>
     */
    public static function config(string $integration): array
    {
        self::load();

        return self::$configs[$integration] ?? [];
    }

    public static function value(string $integration, string $key, ?string $default = null): ?string
    {
        $value = self::config($integration)[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : $default;
    }

    public static function isEnabled(string $integration): bool
    {
        self::load();

        return (self::$enabled[$integration] ?? '0') === '1';
    }

    public static function name(string $integration, ?string $default = null): ?string
    {
        self::load();

        return self::$names[$integration] ?? $default;
    }

    /**
     * Whether the integration was added to the store at all. An integration
     * that was never added has no settings of its own.
     */
    public static function exists(string $integration): bool
    {
        self::load();

        return array_key_exists($integration, self::$enabled);
    }

    /**
     * Drop the per-request cache after an administrator saves a configuration.
     */
    public static function forget(): void
    {
        self::$configs = null;
        self::$names = null;
        self::$enabled = null;
    }

    protected static function load(): void
    {
        if (self::$configs !== null) {
            return;
        }

        self::$configs = [];
        self::$names = [];
        self::$enabled = [];

        $rows = StoreSetting::where('key', 'like', 'integration_%')->pluck('value', 'key');

        foreach ($rows as $key => $value) {
            if (str_starts_with($key, 'integration_config_')) {
                $config = json_decode((string) $value, true);

                if (is_array($config)) {
                    self::$configs[substr($key, strlen('integration_config_'))] = array_filter(
                        $config,
                        fn ($item) => is_scalar($item) && trim((string) $item) !== ''
                    );
                }
            } elseif (str_starts_with($key, 'integration_enabled_')) {
                self::$enabled[substr($key, strlen('integration_enabled_'))] = (string) $value;
            } elseif (str_starts_with($key, 'integration_name_')) {
                self::$names[substr($key, strlen('integration_name_'))] = (string) $value;
            }
        }
    }
}
