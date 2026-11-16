<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Shop extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_id', 'vendor_id', 'name', 'slug', 'logo', 'banner', 'description', 'story',
        'vacation_mode', 'vacation_note',
        'support_email', 'support_phone', 'status',
        'theme', 'layout', 'layout_draft', 'layout_published_at',
    ];

    protected $casts = [
        'vacation_mode' => 'boolean',
        'layout' => 'array',
        'layout_draft' => 'array',
        'layout_published_at' => 'datetime',
    ];

    public function publishedLayout(): array
    {
        return is_array($this->layout) ? $this->layout : [];
    }

    /**
     * The vendor's saved layout for one store tab. A legacy flat layout is
     * treated as the Home region; other regions fall back to the template.
     */
    public function layoutFor(string $region = 'home'): array
    {
        $layout = $this->publishedLayout();

        if ($layout === [] || array_is_list($layout)) {
            return $region === 'home' ? $layout : [];
        }

        $blocks = $layout[$region] ?? [];

        return is_array($blocks) ? $blocks : [];
    }

    public static function boot()
    {
        parent::boot();

        static::creating(function ($shop) {
            if (empty($shop->slug)) {
                $shop->slug = Str::slug($shop->name);
            }
        });
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'shop_id');
    }
}
