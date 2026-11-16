<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;

class Category extends Model
{
    use HasFactory;

    /**
     * Shared cache key used by the storefront navigation menu.
     */
    public const NAV_CACHE_KEY = 'juvo_nav_categories';

    protected $fillable = [
        'slug',
        'parent_category_id',
        'status',
        'icon',
    ];

    protected static function booted()
    {
        static::saved(fn () => cache()->forget(self::NAV_CACHE_KEY));
        static::deleted(fn () => cache()->forget(self::NAV_CACHE_KEY));
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function translations()
    {
        return $this->hasMany(CategoryTranslation::class);
    }

    public function translation()
    {
        return $this->hasOne(CategoryTranslation::class)
            ->where('language_code', App::getLocale());
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_category_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_category_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /**
     * The name shown to shoppers, falling back to the slug.
     */
    public function displayName(): string
    {
        return (string) (optional($this->translation)->name ?? $this->slug);
    }

    /**
     * Sort a loaded category tree alphabetically by display name, at every
     * level. Children are only re-ordered when the relation is already loaded,
     * so eager-loaded nav trees stay cheap.
     *
     * @param  \Illuminate\Support\Collection<int, static>  $categories
     * @return \Illuminate\Support\Collection<int, static>
     */
    public static function sortTreeByName($categories)
    {
        return $categories
            ->sortBy(fn (self $category) => mb_strtolower($category->displayName()))
            ->values()
            ->each(function (self $category) {
                if ($category->relationLoaded('children')) {
                    $category->setRelation('children', self::sortTreeByName($category->children));
                }
            });
    }
}
