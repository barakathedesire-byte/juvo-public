<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageTranslation extends Model
{
    protected $fillable = ['page_id', 'language_code', 'title', 'content', 'image_url'];

    public function setContentAttribute(?string $value): void
    {
        $this->attributes['content'] = \App\Support\Html\HtmlSanitizer::clean($value);
    }

    public function page()
    {
        return $this->belongsTo(Page::class);
    }
}
