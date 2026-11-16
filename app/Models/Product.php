<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',  'brand_id', 'seller_id', 'shop_id', 'price', 'stock', 'status', 'slug', 'currency', 'SKU',
        'weight', 'dimensions', 'product_type',  'image_url', 'vendor_id', 'is_sponsored',
        'label', 'label_style',
        'approval_status', 'rejection_reason', 'visibility', 'submitted_at', 'approved_at',
        'published_at', 'archived_at', 'approved_by',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'published_at' => 'datetime',
        'archived_at' => 'datetime',
        'status' => 'boolean',
    ];

    public const LABEL_STYLES = ['red', 'blue', 'yellow', 'green', 'dark'];

    /**
     * CSS class for the admin-assigned product label badge.
     */
    public function labelBadgeClass(): string
    {
        $style = in_array($this->label_style, self::LABEL_STYLES, true) ? $this->label_style : 'red';

        return 'jv-badge jv-badge-'.$style;
    }

    /**
     * Get the translations for the product.
     */
    public function translations()
    {
        return $this->hasMany(ProductTranslation::class);
    }

    public function translation()
    {
        return $this->hasOne(ProductTranslation::class)
            ->where('language_code', App::getLocale());
    }

    /**
     * Get the category for the product.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    public function inventoryMovements()
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * Only approved + published + public products.
     */
    public function scopePublished($query)
    {
        return $query->where('approval_status', 'approved')
            ->where('visibility', 'public')
            ->whereNull('archived_at')
            ->where(function ($q) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }

    public function isApproved(): bool
    {
        return $this->approval_status === 'approved';
    }

    /**
     * Only products that are active and belong to an approved, active vendor.
     * Used by all customer-facing storefront queries.
     */
    public function scopeVisible($query)
    {
        return $query->where('products.status', 1)
            ->where('products.approval_status', 'approved')
            ->where('products.visibility', 'public')
            ->whereNull('products.archived_at')
            ->where(function ($q) {
                $q->whereNull('products.published_at')->orWhere('products.published_at', '<=', now());
            })
            ->whereHas('vendor', function ($q) {
                $q->where('vendors.status', 'active')
                    ->where('vendors.is_approved', 1);
            });
    }

    // One-to-many relationship with ProductImage
    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    /**
     * Get the brand for the product.
     */
    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function getTranslation($field, $locale = 'en')
    {
        $translation = $this->translations->firstWhere('language_code', $locale);

        return $translation ? $translation->$field : null;
    }

    public function thumbnail()
    {
        return $this->hasOne(ProductImage::class)->where('type', 'thumb');
    }

    public function savedByAgents()
    {
        return $this->hasMany(\App\Models\AgentSavedProduct::class, 'product_id');
    }

    public function reviews()
    {
        return $this->hasMany(ProductReview::class)->approved()->latest();
    }

    public function averageRating()
    {
        return $this->reviews()->avg('rating') ?: 0;
    }

    public function getConvertedPriceAttribute()
    {
        return convert_price($this->price);
    }

    public function getConvertedDiscountPriceAttribute()
    {
        return $this->discount_price ? convert_price($this->discount_price) : null;
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    /*public function attributeValues()
    {
        return $this->belongsToMany(AttributeValue::class, 'product_attribute_values', 'product_id', 'attribute_value_id');
    }*/
    public function attributeValues()
    {
        return $this->belongsToMany(AttributeValue::class, 'product_attribute_values')
            ->with('attribute', 'translations');
    }

    public function primaryVariant()
    {
        return $this->hasOne(ProductVariant::class)->where('is_primary', true);
    }

    public function searchDocuments()
    {
        return $this->hasMany(ProductSearchDocument::class);
    }

    public function wishlistedBy()
    {
        return $this->belongsToMany(Customer::class, 'wishlists');
    }

    /**
     * Active promotions that apply to this product. A product is only shown
     * with a sale price when one of these vendor/category/platform rules is
     * actually running, rather than relying on a stale variant discount.
     */
    public function activePromotions(): \Illuminate\Support\Collection
    {
        return Promotion::query()
            ->running()
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->where('scope', 'product')->where('product_id', $this->getKey());
                })->orWhere(function ($q) {
                    $q->where('scope', 'category')
                        ->whereNotNull('category_id')
                        ->where('category_id', $this->category_id);
                })->orWhere(function ($q) {
                    $q->where('scope', 'vendor')
                        ->whereNotNull('vendor_id')
                        ->where('vendor_id', $this->vendor_id);
                });
            })
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->get();
    }

    public function activePromotion(): ?Promotion
    {
        return $this->activePromotions()->first();
    }

    public function hasActivePromotion(): bool
    {
        return $this->activePromotions()->isNotEmpty();
    }
}
