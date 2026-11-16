<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class VendorPayoutMethod extends Model
{
    protected $fillable = ['vendor_id', 'method', 'provider', 'account_name', 'account_number', 'email', 'is_default', 'active'];

    protected $casts = [
        'is_default' => 'boolean',
        'active' => 'boolean',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * Account numbers are stored encrypted at rest.
     */
    public function getAccountNumberAttribute($value)
    {
        if ($value === null || $value === '' || self::looksEncrypted($value)) {
            if ($value === null || $value === '') {
                return $value;
            }

            try {
                return Crypt::decryptString($value);
            } catch (\Throwable $e) {
                return $value;
            }
        }

        // Legacy plaintext value stored before encryption was enabled.
        return $value;
    }

    public function setAccountNumberAttribute($value)
    {
        if ($value === null || $value === '' || self::looksEncrypted($value)) {
            $this->attributes['account_number'] = $value;

            return;
        }

        $this->attributes['account_number'] = Crypt::encryptString($value);
    }

    protected static function looksEncrypted(string $value): bool
    {
        return str_starts_with($value, 'eyJ');
    }
}
