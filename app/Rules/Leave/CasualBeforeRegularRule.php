<?php

namespace App\Rules\Leave;

use App\Models\Employee;
use App\Models\LeaveType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Warns (never blocks) when:
 *   - The requested leave type is 'regular'
 *   - The request is for 1-2 days
 *   - The employee still has casual leave balance available
 *
 * Per domain rule: "إجازة اعتيادية لمدة 1-2 أيام لا تُمنح إلا بعد استنفاد
 * رصيد الإجازة العارضة."
 *
 * config('leave.casual_before_regular_warning') must be true to activate.
 */
class CasualBeforeRegularRule implements ValidationRule
{
    public ?string $warning = null;

    public function __construct(
        private readonly Employee $employee,
        private readonly LeaveType $requestedType,
        private readonly int $year,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! config('leave.casual_before_regular_warning', true)) {
            return;
        }

        // Only applies to regular leave of 1-2 days
        if ($this->requestedType->code !== 'regular') {
            return;
        }

        if ((float) $value > 2) {
            return;
        }

        // Check if casual leave type exists and employee has remaining balance
        $casualType = LeaveType::where('code', 'casual')->where('is_active', true)->first();

        if (! $casualType) {
            return;
        }

        $casualBalance = $this->employee->balanceFor($casualType->id, $this->year);

        if ($casualBalance && $casualBalance->remaining > 0) {
            $this->warning = sprintf(
                'ينبغي استخدام رصيد الإجازة العارضة (المتبقي: %.1f يوم) أولاً قبل الاعتيادية للطلبات القصيرة (1-2 يوم).',
                $casualBalance->remaining
            );
        }
    }
}
