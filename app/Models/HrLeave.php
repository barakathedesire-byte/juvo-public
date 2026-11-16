<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrLeave extends Model
{
    protected $table = 'hr_leaves';

    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'start_date',
        'end_date',
        'days',
        'status',
        'reason',
        'approved_by',
        'approved_at',
        'remarks',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
        'days' => 'integer',
    ];

    public const STATUSES = ['pending', 'approved', 'rejected', 'cancelled'];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function leaveType()
    {
        return $this->belongsTo(HrLeaveType::class, 'leave_type_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
