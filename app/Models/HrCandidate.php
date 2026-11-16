<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrCandidate extends Model
{
    protected $table = 'hr_candidates';

    protected $fillable = [
        'job_post_id',
        'name',
        'email',
        'phone',
        'resume_path',
        'status',
        'notes',
    ];

    public const STATUSES = ['applied', 'screened', 'interview', 'offered', 'hired', 'rejected', 'withdrawn'];

    public function jobPost()
    {
        return $this->belongsTo(HrJobPost::class, 'job_post_id');
    }
}
