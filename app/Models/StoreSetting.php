<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class StoreSetting extends Model
{
    use HasFactory;

    protected $table = 'store_settings';

    public $timestamps = false;

    protected $fillable = ['key', 'value'];

    protected static function booted(): void
    {
        $flush = function (StoreSetting $setting) {
            Cache::forget('store_setting_'.$setting->key);
            Cache::forget('web_config_'.$setting->key);
        };

        static::saved($flush);
        static::deleted($flush);
    }
}
