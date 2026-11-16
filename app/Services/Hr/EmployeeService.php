<?php

namespace App\Services\Hr;

use App\Models\Employee;
use Illuminate\Validation\ValidationException;

/**
 * Employee record operations. Enforces the reporting-hierarchy invariants:
 * an employee may never report to itself, directly or transitively.
 */
class EmployeeService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Employee
    {
        $this->guardManager(null, $data['manager_id'] ?? null);

        return Employee::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Employee $employee, array $data): Employee
    {
        if (array_key_exists('manager_id', $data)) {
            $this->guardManager($employee, $data['manager_id'] ?? null);
        }

        $employee->fill($data)->save();

        return $employee;
    }

    /**
     * Reject a management link that would introduce a cycle (including
     * self-management).
     *
     * @throws ValidationException
     */
    public function guardManager(?Employee $employee, ?int $managerId): void
    {
        if ($managerId === null) {
            return;
        }

        if ($employee && $managerId === $employee->id) {
            throw ValidationException::withMessages([
                'manager_id' => 'An employee cannot report to themselves.',
            ]);
        }

        $manager = Employee::find($managerId);
        if (! $manager) {
            return;
        }

        if (! $employee) {
            // A brand-new employee cannot already appear in an existing chain.
            return;
        }

        // Walk up from the proposed manager; reaching the employee is a cycle.
        $seen = [];
        $cursor = $manager;

        while ($cursor) {
            if ($cursor->id === $employee->id) {
                throw ValidationException::withMessages([
                    'manager_id' => 'This reporting line would create a circular hierarchy.',
                ]);
            }

            if (isset($seen[$cursor->id])) {
                // Pre-existing cycle in the data; refuse to extend it.
                break;
            }
            $seen[$cursor->id] = true;

            $cursor = $cursor->manager_id ? Employee::find($cursor->manager_id) : null;
        }
    }
}
