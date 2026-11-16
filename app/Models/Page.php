<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $fillable = [
        'slug', 'status', 'page_type', 'template', 'parent_id', 'is_system',
        'system_key', 'seo_title', 'seo_description', 'seo_image', 'blocks',
        'settings', 'sort_order', 'published_at',
    ];

    protected $casts = [
        'status' => 'boolean',
        'is_system' => 'boolean',
        'blocks' => 'array',
        'settings' => 'array',
        'sort_order' => 'integer',
        'published_at' => 'datetime',
    ];

    public const TYPES = [
        'custom' => 'Custom landing page',
        'promotional' => 'Promotional page',
        'collection' => 'Collection page',
        'category_landing' => 'Category landing page',
        'informational' => 'Informational page',
        'system' => 'System page',
    ];

    public const TEMPLATES = [
        'default' => 'Default (built with blocks)',
        'content' => 'Rich content (single article)',
    ];

    /**
     * Templates available for the product system page.
     */
    public static function productTemplates(): array
    {
        return \App\Services\Pages\ProductTemplates::options();
    }

    public function translations()
    {
        return $this->hasMany(PageTranslation::class);
    }

    public function translation($languageCode = null)
    {
        $languageCode = $languageCode ?? app()->getLocale();

        return $this->translations()->where('language_code', $languageCode)->first();
    }

    public function revisions()
    {
        return $this->hasMany(PageRevision::class)->latest();
    }

    public function parent()
    {
        return $this->belongsTo(Page::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Page::class, 'parent_id');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 1);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function title(): string
    {
        $translated = optional($this->translations->firstWhere('language_code', app()->getLocale())
            ?? $this->translations->first())->title;

        if ($translated) {
            return $translated;
        }

        if ($this->isSystem() && $this->system_key) {
            $name = \App\Services\Pages\PageRegistry::systemDefinitions()[$this->system_key]['name'] ?? null;

            if ($name) {
                return $name;
            }
        }

        return $this->slug;
    }

    public function hasBuilder(): bool
    {
        return is_array($this->blocks) && count($this->blocks) > 0;
    }

    public function isSystem(): bool
    {
        return (bool) $this->is_system;
    }

    public function publicUrl(): ?string
    {
        if ($this->isSystem()) {
            return null;
        }

        return route('page.show', $this->slug);
    }
}
