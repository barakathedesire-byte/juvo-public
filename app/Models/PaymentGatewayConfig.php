<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class PaymentGatewayConfig extends Model
{
    protected $fillable = ['gateway_id', 'key_name', 'key_value', 'is_encrypted', 'environment'];

    protected static function booted(): void
    {
        // Encrypt at save time so the result does not depend on the order in
        // which `key_value` and `is_encrypted` are assigned during mass-update.
        static::saving(function (self $config) {
            if (! $config->is_encrypted) {
                return;
            }

            $value = $config->attributes['key_value'] ?? null;

            if ($value === null || $value === '' || self::looksEncrypted($value)) {
                return;
            }

            $config->attributes['key_value'] = Crypt::encryptString($value);
        });
    }

    public function gateway()
    {
        return $this->belongsTo(PaymentGateway::class, 'gateway_id');
    }

    public function getKeyValueAttribute($value)
    {
        if ($value === null || $value === '' || ! $this->is_encrypted) {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable $e) {
            // Value stored before encryption was enabled, or already plaintext.
            return $value;
        }
    }

    public function setKeyValueAttribute($value)
    {
        $this->attributes['key_value'] = $value;
    }

    protected static function looksEncrypted(string $value): bool
    {
        // Laravel encrypter output is base64 JSON starting with {"iv": ...}.
        return str_starts_with($value, 'eyJ');
    }
}
