<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomepageTile extends Model
{
    protected $fillable = [
        'title', 'subtitle', 'image', 'bg_color', 'text_color', 'size', 'shape',
        'link_type', 'link_value', 'status', 'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
