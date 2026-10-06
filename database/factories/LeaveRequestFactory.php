<?php

namespace Database\Factories;

use App\Enums\LeaveStatus;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class LeaveRequestFactory extends Factory
{
    protected $model = LeaveRequest::class;

    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('2026-01-01', '2026-12-01');
        $endDate   = $this->faker->dateTimeBetween($startDate, (clone $startDate)->modify('+14 days'));
        $days      = max(1, (int) $startDate->diff($endDate)->days + 1);

        return [
            'number'                 => 'LV-' . now()->year . '-' . str_pad($this->faker->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'employee_id'            => Employee::factory(),
            'organization_id'        => Organization::factory(),
            'leave_type_id'          => LeaveType::factory(),
            'substitute_employee_id' => null,
            'start_date'             => $startDate,
            'end_date'               => $endDate,
            'days'                   => $days,
            'written_at'             => $this->faker->dateTimeBetween('-30 days', 'now'),
            'reason'                 => $this->faker->optional(0.7)->randomElement([
                'ظروف عائلية',
                'إجراءات شخصية',
                'مناسبة اجتماعية',
                'أعمال خاصة',
                'السفر خارج المحافظة',
            ]),
            'balance_entitled'       => 45,
            'balance_used'           => $this->faker->numberBetween(0, 30),
            'balance_remaining'      => $this->faker->numberBetween(5, 45),
            'status'                 => LeaveStatus::DRAFT,
            'current_stage'          => null,
            'rejection_reason'       => null,
            'created_by'             => User::factory(),
            'submitted_at'           => null,
            'decided_at'             => null,
            'decided_by'             => null,
        ];
    }

    // -------------------------------------------------------------------------
    // States
    // -------------------------------------------------------------------------

    public function submitted(): static
    {
        return $this->state(fn() => [
            'status'       => LeaveStatus::SUBMITTED,
            'current_stage' => 'direct_manager',
            'submitted_at' => now()->subHours(rand(1, 72)),
        ]);
    }

    public function inReview(): static
    {
        return $this->state(fn() => [
            'status'       => LeaveStatus::IN_REVIEW,
            'current_stage' => 'leaves_officer',
            'submitted_at' => now()->subDays(rand(1, 5)),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn() => [
            'status'       => LeaveStatus::APPROVED,
            'current_stage' => null,
            'submitted_at' => now()->subDays(rand(3, 14)),
            'decided_at'   => now()->subDays(rand(1, 3)),
        ]);
    }

    public function rejected(string $reason = 'لا يوجد رصيد كافٍ'): static
    {
        return $this->state(fn() => [
            'status'           => LeaveStatus::REJECTED,
            'current_stage'    => null,
            'rejection_reason' => $reason,
            'submitted_at'     => now()->subDays(rand(3, 14)),
            'decided_at'       => now()->subDays(rand(1, 3)),
        ]);
    }

    public function returned(): static
    {
        return $this->state(fn() => [
            'status'       => LeaveStatus::RETURNED,
            'current_stage' => 'direct_manager',
            'submitted_at' => now()->subDays(rand(1, 7)),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn() => [
            'status'       => LeaveStatus::CANCELLED,
            'current_stage' => null,
        ]);
    }

    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn() => [
            'employee_id'    => $employee->id,
            'organization_id' => $employee->organization_id,
        ]);
    }
}
