<?php

namespace App\Models;

use App\Services\Store\StoreBlocks;
use Illuminate\Database\Eloquent\Model;

class StorefrontTemplate extends Model
{
    public const SCOPE_GLOBAL = 'global';

    public const SCOPE_VENDOR = 'vendor';

    /**
     * Store tabs the builder can manage. "home" is the store landing page;
     * every other region backs one tab on the store header.
     */
    public const REGIONS = [
        'home' => 'Home',
        'products' => 'Products',
        'collections' => 'Collections',
        'about' => 'About',
        'shipping' => 'Shipping',
        'returns' => 'Returns',
    ];

    protected $fillable = [
        'name', 'scope', 'vendor_id', 'blocks', 'status', 'published_at', 'created_by',
    ];

    protected $casts = [
        'blocks' => 'array',
        'status' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function scopePublished($query)
    {
        return $query->where('status', 1)->whereNotNull('published_at');
    }

    public function sanitizedBlocks(): array
    {
        return $this->sanitizedBlocksFor('home');
    }

    /**
     * Region map normalised from the stored payload. A legacy flat block list
     * is treated as the Home region.
     */
    public function regionMap(): array
    {
        $blocks = is_array($this->blocks) ? $this->blocks : [];

        if ($blocks === []) {
            return [];
        }

        return array_is_list($blocks) ? ['home' => $blocks] : $blocks;
    }

    public function blocksFor(string $region): array
    {
        $blocks = $this->regionMap()[$region] ?? [];

        return is_array($blocks) ? $blocks : [];
    }

    public function sanitizedBlocksFor(string $region): array
    {
        return StoreBlocks::sanitize($this->blocksFor($region));
    }

    /**
     * Sanitised blocks for every region, ready for the builder UI.
     */
    public function sanitizedRegions(): array
    {
        $out = [];

        foreach (array_keys(self::REGIONS) as $region) {
            $out[$region] = $this->sanitizedBlocksFor($region);
        }

        return $out;
    }

    public function setRegionMap(array $map): void
    {
        $this->blocks = $map;
    }

    /**
     * Resolve the active storefront template for a vendor: an explicit
     * vendor-scoped template wins over the global template.
     */
    public static function resolveForVendor(?int $vendorId): ?self
    {
        if ($vendorId) {
            $vendorTemplate = static::query()
                ->published()
                ->where('scope', self::SCOPE_VENDOR)
                ->where('vendor_id', $vendorId)
                ->latest('published_at')
                ->first();

            if ($vendorTemplate) {
                return $vendorTemplate;
            }
        }

        return static::query()
            ->published()
            ->where('scope', self::SCOPE_GLOBAL)
            ->latest('published_at')
            ->first();
    }
}
