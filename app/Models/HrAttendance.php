<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrAttendance extends Model
{
    protected $table = 'hr_attendance';

    protected $fillable = [
        'employee_id',
        'attendance_date',
        'clock_in_at',
        'clock_out_at',
        'status',
        'source',
        'notes',
        'recorded_by',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'clock_in_at' => 'datetime',
        'clock_out_at' => 'datetime',
    ];

    public const STATUSES = ['present', 'absent', 'late', 'half_day'];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
