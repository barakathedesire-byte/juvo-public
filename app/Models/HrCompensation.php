<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrCompensation extends Model
{
    use HasFactory;

    protected $table = 'hr_compensations';

    public const FREQUENCIES = ['monthly', 'biweekly', 'weekly', 'daily', 'hourly', 'annual'];

    protected $fillable = [
        'employee_id',
        'amount',
        'currency',
        'pay_frequency',
        'effective_from',
        'effective_to',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
