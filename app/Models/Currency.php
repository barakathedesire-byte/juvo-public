<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Currency extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'symbol', 'exchange_rate'];

    public function setCodeAttribute($value): void
    {
        $this->attributes['code'] = strtoupper(trim((string) $value));
    }

    protected static function booted(): void
    {
        $flush = function (Currency $currency) {
            Cache::forget('currency_USD');
            Cache::forget('currency_'.$currency->code);
            Cache::forget('currency_'.strtoupper((string) $currency->code));
            Cache::forget('active_currency_'.$currency->code);
            Cache::forget('active_currency_'.strtoupper((string) $currency->code));
        };

        static::saved($flush);
        static::deleted($flush);
    }
}
