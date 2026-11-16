<?php

namespace App\Support\Ads;

/**
 * Integer minor-unit money helpers for the Juvo Ads engine.
 *
 * All advertising financial values are stored as integer minor units (e.g.
 * kobo/cents) alongside an explicit ISO-4217 currency. Money is never carried
 * as a float inside the engine; decimals only appear at the boundary with the
 * legacy marketplace columns, where they are parsed exactly.
 */
final class Money
{
    public const DEFAULT_CURRENCY = 'TZS';

    public const SCALE = 2;

    /**
     * Parse a decimal string/number into integer minor units without floating
     * point drift.
     */
    public static function minor($value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_int($value)) {
            return $value * (10 ** self::SCALE);
        }

        $string = is_string($value) ? trim($value) : number_format((float) $value, self::SCALE + 2, '.', '');

        $negative = false;
        if (str_starts_with($string, '-')) {
            $negative = true;
            $string = substr($string, 1);
        } elseif (str_starts_with($string, '+')) {
            $string = substr($string, 1);
        }

        $parts = explode('.', $string, 2);
        $whole = preg_replace('/\D/', '', $parts[0]);
        $fraction = isset($parts[1]) ? preg_replace('/\D/', '', $parts[1]) : '';
        $fraction = str_pad(substr($fraction, 0, self::SCALE + 1), self::SCALE + 1, '0');

        // Round half-up on the third decimal digit, using integer math only.
        $keep = (int) substr($fraction, 0, self::SCALE);
        $next = (int) $fraction[self::SCALE];

        $minor = ((int) $whole) * (10 ** self::SCALE) + $keep;
        if ($next >= 5) {
            $minor++;
        }

        return $negative ? -$minor : $minor;
    }

    /**
     * Render minor units back to a decimal string (integer-safe).
     */
    public static function decimal(int $minor): string
    {
        $sign = $minor < 0 ? '-' : '';
        $abs = abs($minor);
        $whole = intdiv($abs, 10 ** self::SCALE);
        $frac = $abs % (10 ** self::SCALE);

        return $sign.$whole.'.'.str_pad((string) $frac, self::SCALE, '0', STR_PAD_LEFT);
    }

    public static function format(int $minor, ?string $currency = null): string
    {
        $currency = $currency ?: self::DEFAULT_CURRENCY;

        return strtoupper($currency).' '.number_format(self::decimal($minor), self::SCALE);
    }
}
