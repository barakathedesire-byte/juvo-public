<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductTranslation extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'locale',
        'language_code',
        'name',
        'description',
        'short_description',
        'tags',
    ];

    public function setDescriptionAttribute(?string $value): void
    {
        $this->attributes['description'] = \App\Support\Html\HtmlSanitizer::clean($value);
    }

    public function setShortDescriptionAttribute(?string $value): void
    {
        $this->attributes['short_description'] = $value === null ? null : trim(strip_tags($value));
    }

    /**
     * Get the product that owns the translation.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
