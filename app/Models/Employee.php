<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ON_LEAVE = 'on_leave';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_TERMINATED = 'terminated';

    public const EMPLOYMENT_TYPES = ['full_time', 'part_time', 'contract', 'intern'];

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_ON_LEAVE,
        self::STATUS_SUSPENDED,
        self::STATUS_TERMINATED,
    ];

    protected $fillable = [
        'employee_number',
        'user_id',
        'department_id',
        'position_id',
        'manager_id',
        'employment_type',
        'hire_date',
        'end_date',
        'status',
        'work_email',
        'work_phone',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'end_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function department()
    {
        return $this->belongsTo(StaffDepartment::class, 'department_id');
    }

    public function position()
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function manager()
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    public function subordinates()
    {
        return $this->hasMany(self::class, 'manager_id');
    }

    public function documents()
    {
        return $this->hasMany(EmployeeDocument::class, 'employee_id');
    }

    public function compensations()
    {
        return $this->hasMany(HrCompensation::class, 'employee_id');
    }

    public function bankDetails()
    {
        return $this->hasMany(EmployeeBankDetail::class, 'employee_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function displayName(): string
    {
        return $this->user?->name ?? $this->employee_number;
    }
}
