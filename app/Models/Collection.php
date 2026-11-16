<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Collection extends Model
{
    protected $fillable = [
        'vendor_id', 'name', 'slug', 'image', 'bg_color', 'text_color', 'size', 'shape',
        'rule_type', 'rule_value', 'status', 'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function scopeMarketplace($query)
    {
        return $query->whereNull('vendor_id');
    }

    public function scopeForVendor($query, $vendorId)
    {
        return $query->where('vendor_id', $vendorId);
    }

    public const RULES = [
        'newest' => 'Newest products',
        'discounted' => 'Discounted deals',
        'hidden_gems' => 'Curated discoveries (top rated)',
        'sponsored' => 'Featured / sponsored',
        'category' => 'By category',
        'brand' => 'By brand',
        'all' => 'Everything',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Apply this collection's rule to a product query. Mirrors the built-in
     * preset semantics used by ShopController so named collections behave
     * exactly like the hardcoded ones.
     */
    public function applyTo($query): void
    {
        $discounted = function ($q) {
            $q->whereHas('variants', function ($vq) {
                $vq->whereNotNull('discount_price')
                    ->whereColumn('discount_price', '<', 'price');
            });
        };

        switch ($this->rule_type) {
            case 'discounted':
                $discounted($query);
                $query->orderByDesc('id');
                break;
            case 'hidden_gems':
                $query->orderByDesc('reviews_avg_rating')->orderByDesc('reviews_count');
                break;
            case 'sponsored':
                $query->where('is_sponsored', 1)->orderByDesc('id');
                break;
            case 'category':
                $query->whereIn('category_id', $this->categoryIds());
                break;
            case 'brand':
                if ($this->rule_value !== null && $this->rule_value !== '') {
                    $query->where('brand_id', (int) $this->rule_value);
                }
                break;
            case 'newest':
                $query->latest();
                break;
            case 'all':
            default:
                break;
        }
    }

    /**
     * Resolve the selected category (and its descendants) to a list of ids.
     */
    protected function categoryIds(): array
    {
        if (! $this->rule_value) {
            return [];
        }

        $root = Category::where('slug', $this->rule_value)->first();

        if (! $root) {
            return [];
        }

        $ids = [$root->id];

        $children = Category::where('parent_category_id', $root->id)->pluck('id')->all();

        return array_merge($ids, $children);
    }
}
