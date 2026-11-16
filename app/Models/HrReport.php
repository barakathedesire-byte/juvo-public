<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrReport extends Model
{
    protected $table = 'hr_reports';

    protected $fillable = [
        'name',
        'slug',
        'type',
        'filters',
        'permissions',
        'is_public',
        'created_by',
    ];

    protected $casts = [
        'filters' => 'array',
        'permissions' => 'array',
        'is_public' => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
