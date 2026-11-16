<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Employee bank details. The account number is encrypted at rest (Laravel
 * `encrypted` cast) and must never be shown in full, logged or exported — only
 * a masked form (last four digits) is ever rendered.
 */
class EmployeeBankDetail extends Model
{
    use HasFactory;

    protected $table = 'employee_bank_details';

    protected $fillable = [
        'employee_id',
        'bank_name',
        'account_name',
        'account_number',
        'branch',
        'swift_code',
        'currency',
        'is_primary',
        'created_by',
    ];

    protected $casts = [
        'account_number' => 'encrypted',
        'is_primary' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * A safe display form of the account number: only the last four digits.
     */
    public function maskedAccountNumber(): string
    {
        $number = (string) $this->account_number;
        $digits = preg_replace('/\s+/', '', $number) ?? '';

        if ($digits === '') {
            return '—';
        }

        $last4 = substr($digits, -4);

        return str_repeat('*', max(0, strlen($digits) - 4)).$last4;
    }

    public function lastFour(): string
    {
        $digits = preg_replace('/\s+/', '', (string) $this->account_number) ?? '';

        return substr($digits, -4);
    }
}
