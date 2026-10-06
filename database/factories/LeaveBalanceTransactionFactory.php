<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\LeaveBalance;
use App\Models\LeaveBalanceTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveBalanceTransactionFactory extends Factory
{
    protected $model = LeaveBalanceTransaction::class;

    public function definition(): array
    {
        return [
            'leave_balance_id'  => LeaveBalance::factory(),
            'leave_request_id'  => null,
            'type'              => $this->faker->randomElement(TransactionType::cases()),
            'days'              => $this->faker->numberBetween(1, 10),
            'note'              => $this->faker->optional(0.6)->sentence(),
            'created_by'        => null,
        ];
    }

    public function accrual(int $days): static
    {
        return $this->state(fn() => [
            'type' => TransactionType::ACCRUAL,
            'days' => $days,
            'note' => 'استحقاق سنوي',
        ]);
    }

    public function deduction(int $days): static
    {
        return $this->state(fn() => [
            'type' => TransactionType::DEDUCTION,
            'days' => $days,
            'note' => 'خصم بسبب إجازة معتمدة',
        ]);
    }

    public function refund(int $days): static
    {
        return $this->state(fn() => [
            'type' => TransactionType::REFUND,
            'days' => $days,
            'note' => 'استرداد بسبب إلغاء إجازة',
        ]);
    }

    public function carryover(int $days): static
    {
        return $this->state(fn() => [
            'type' => TransactionType::CARRYOVER,
            'days' => $days,
            'note' => 'ترحيل رصيد من السنة السابقة',
        ]);
    }
}
