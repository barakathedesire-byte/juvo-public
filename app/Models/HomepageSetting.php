<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class HomepageSetting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get(string $key, $default = null)
    {
        $value = Cache::rememberForever("homepage_setting_{$key}", function () use ($key) {
            return static::where('key', $key)->value('value');
        });

        return $value ?? $default;
    }

    public static function put(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("homepage_setting_{$key}");
        Cache::forget('homepage_config');
    }

    public static function json(string $key, array $default = []): array
    {
        $value = static::get($key);

        if (! is_string($value) || $value === '') {
            return $default;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : $default;
    }
}
