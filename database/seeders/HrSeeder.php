<?php

namespace Database\Seeders;

use App\Models\HrLeaveType;
use Illuminate\Database\Seeder;

/**
 * Default HR lookup data. Only generic, configuration-level records are seeded
 * here (leave categories). No statutory rates, contributions or invented
 * jurisdiction rules are created.
 */
class HrSeeder extends Seeder
{
    public const LEAVE_TYPES = [
        ['name' => 'Annual leave', 'code' => 'annual', 'is_paid' => true, 'max_days' => null, 'requires_approval' => true],
        ['name' => 'Sick leave', 'code' => 'sick', 'is_paid' => true, 'max_days' => null, 'requires_approval' => true],
        ['name' => 'Maternity leave', 'code' => 'maternity', 'is_paid' => true, 'max_days' => null, 'requires_approval' => true],
        ['name' => 'Paternity leave', 'code' => 'paternity', 'is_paid' => true, 'max_days' => null, 'requires_approval' => true],
        ['name' => 'Unpaid leave', 'code' => 'unpaid', 'is_paid' => false, 'max_days' => null, 'requires_approval' => true],
    ];

    public function run(): void
    {
        foreach (self::LEAVE_TYPES as $type) {
            HrLeaveType::firstOrCreate(['code' => $type['code']], $type);
        }
    }
}
