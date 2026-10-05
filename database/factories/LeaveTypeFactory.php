<?php

namespace Database\Factories;

use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveTypeFactory extends Factory
{
    protected $model = LeaveType::class;

    public function definition(): array
    {
        return [
            'code'                 => $this->faker->unique()->bothify('LT-??'),
            'name'                 => $this->faker->randomElement(['إجازة اعتيادية', 'إجازة عارضة', 'إجازة مرضية']),
            'deducts_balance'      => true,
            'yearly_entitlement'   => $this->faker->randomElement([21, 30, 45]),
            'max_days_per_request' => $this->faker->randomElement([null, 7, 15, 30]),
            'is_active'            => true,
        ];
    }

    // -------------------------------------------------------------------------
    // States — match the real leave types used by the domain
    // -------------------------------------------------------------------------

    public function regular(): static
    {
        return $this->state(fn() => [
            'code'                 => 'regular',
            'name'                 => 'إجازة اعتيادية',
            'deducts_balance'      => true,
            'yearly_entitlement'   => 45,
            'max_days_per_request' => null,
        ]);
    }

    public function casual(): static
    {
        return $this->state(fn() => [
            'code'                 => 'casual',
            'name'                 => 'إجازة عارضة',
            'deducts_balance'      => true,
            'yearly_entitlement'   => 7,
            'max_days_per_request' => 7,
        ]);
    }

    public function sick(): static
    {
        return $this->state(fn() => [
            'code'                 => 'sick',
            'name'                 => 'إجازة مرضية',
            'deducts_balance'      => false,
            'yearly_entitlement'   => 180,
            'max_days_per_request' => null,
        ]);
    }

    public function emergency(): static
    {
        return $this->state(fn() => [
            'code'                 => 'emergency',
            'name'                 => 'إجازة طارئة',
            'deducts_balance'      => true,
            'yearly_entitlement'   => 5,
            'max_days_per_request' => 3,
        ]);
    }
}
