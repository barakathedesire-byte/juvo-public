<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrLeaveType extends Model
{
    protected $table = 'hr_leave_types';

    protected $fillable = [
        'name',
        'code',
        'is_paid',
        'max_days',
        'requires_approval',
    ];

    protected $casts = [
        'is_paid' => 'boolean',
        'requires_approval' => 'boolean',
        'max_days' => 'integer',
    ];

    public function leaves()
    {
        return $this->hasMany(HrLeave::class, 'leave_type_id');
    }
}
