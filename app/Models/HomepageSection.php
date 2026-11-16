<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class HomepageSection extends Model
{
    protected $fillable = [
        'type', 'title', 'subtitle', 'icon', 'source', 'category_id',
        'layout', 'limit', 'settings', 'status', 'sort_order',
        'starts_at', 'ends_at', 'auto_delete',
    ];

    protected $casts = [
        'settings' => 'array',
        'limit' => 'integer',
        'sort_order' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'auto_delete' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function isLive(): bool
    {
        if ($this->status !== 'published') {
            return false;
        }
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }
        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Delete every image owned by this section's settings.
     */
    public function purgeImages(): void
    {
        $settings = $this->settings ?? [];

        $paths = collect([
            $settings['image'] ?? null,
        ]);

        foreach (['cards', 'banners'] as $group) {
            foreach (($settings[$group] ?? []) as $item) {
                $paths->push($item['image'] ?? null);
            }
        }

        $paths->merge($settings['collection_images'] ?? [])
            ->filter()
            ->unique()
            ->each(function ($path) {
                if (! is_string($path) || preg_match('#^https?://#i', $path)) {
                    return;
                }
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            });
    }

    /**
     * Remove sections whose timed run has ended and were marked for cleanup.
     */
    public static function purgeExpired(): int
    {
        $sections = static::query()
            ->where('auto_delete', true)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', now())
            ->get();

        $deleted = 0;

        foreach ($sections as $section) {
            $section->purgeImages();
            $section->delete();
            $deleted++;
        }

        return $deleted;
    }
}
