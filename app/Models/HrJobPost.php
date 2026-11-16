<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrJobPost extends Model
{
    protected $table = 'hr_job_posts';

    protected $fillable = [
        'title',
        'department_id',
        'position_id',
        'description',
        'employment_type',
        'location',
        'is_active',
        'openings',
        'closes_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'openings' => 'integer',
        'closes_at' => 'date',
    ];

    public function department()
    {
        return $this->belongsTo(StaffDepartment::class, 'department_id');
    }

    public function position()
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function candidates()
    {
        return $this->hasMany(HrCandidate::class, 'job_post_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
