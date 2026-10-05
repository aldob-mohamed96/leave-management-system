<?php

namespace Database\Factories;

use App\Enums\StepStatus;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestStep;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveRequestStepFactory extends Factory
{
    protected $model = LeaveRequestStep::class;

    public function definition(): array
    {
        return [
            'leave_request_id' => LeaveRequest::factory(),
            'step_order'       => 1,
            'stage'            => 'direct_manager',
            'status'           => StepStatus::PENDING,
            'acted_by'         => null,
            'acted_at'         => null,
            'note'             => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn() => [
            'status'   => StepStatus::APPROVED,
            'acted_by' => User::factory(),
            'acted_at' => now()->subHours(rand(1, 24)),
            'note'     => $this->faker->optional(0.4)->randomElement([
                'تمت الموافقة',
                'لا مانع',
                'موافق',
            ]),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn() => [
            'status'   => StepStatus::REJECTED,
            'acted_by' => User::factory(),
            'acted_at' => now()->subHours(rand(1, 24)),
            'note'     => $this->faker->randomElement([
                'لا يوجد رصيد كافٍ',
                'يوجد ضغط عمل في هذه الفترة',
                'الطلب غير مستوفٍ للشروط',
            ]),
        ]);
    }

    public function returned(): static
    {
        return $this->state(fn() => [
            'status'   => StepStatus::RETURNED,
            'acted_by' => User::factory(),
            'acted_at' => now()->subHours(rand(1, 24)),
            'note'     => 'يرجى إرفاق المستندات الداعمة',
        ]);
    }

    public function forStage(string $stage, int $order): static
    {
        return $this->state(fn() => [
            'stage'      => $stage,
            'step_order' => $order,
        ]);
    }
}
