<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageRevision extends Model
{
    protected $fillable = [
        'page_id', 'title', 'blocks', 'seo_title', 'seo_description',
        'seo_image', 'status', 'note', 'created_by',
    ];

    protected $casts = [
        'blocks' => 'array',
    ];

    public function page()
    {
        return $this->belongsTo(Page::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
