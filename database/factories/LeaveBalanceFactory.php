<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveBalanceFactory extends Factory
{
    protected $model = LeaveBalance::class;

    public function definition(): array
    {
        $entitled    = $this->faker->randomElement([7.0, 21.0, 30.0, 45.0]);
        $carriedOver = $this->faker->randomFloat(1, 0, 10);
        $used        = $this->faker->randomFloat(1, 0, $entitled);

        return [
            'employee_id'   => Employee::factory(),
            'leave_type_id' => LeaveType::factory(),
            'year'          => now()->year,
            'entitled'      => $entitled,
            'carried_over'  => $carriedOver,
            'used'          => $used,
        ];
    }

    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn() => ['employee_id' => $employee->id]);
    }

    public function forLeaveType(LeaveType $leaveType): static
    {
        return $this->state(fn() => ['leave_type_id' => $leaveType->id]);
    }

    public function forYear(int $year): static
    {
        return $this->state(fn() => ['year' => $year]);
    }

    /** Full balance — nothing used yet */
    public function fresh(): static
    {
        return $this->state(fn() => [
            'used'        => 0,
            'carried_over' => 0,
        ]);
    }

    /** Exhausted balance */
    public function exhausted(): static
    {
        return $this->state(function (array $attrs) {
            return ['used' => $attrs['entitled'] + $attrs['carried_over']];
        });
    }
}
