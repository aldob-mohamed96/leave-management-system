<?php

namespace App\Rules\Leave;

use App\Models\LeaveType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects a request whose day count exceeds the leave type's per-request cap.
 */
class MaxDaysPerRequestRule implements ValidationRule
{
    public function __construct(
        private readonly LeaveType $leaveType,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $max = $this->leaveType->max_days_per_request;

        if ($max === null) {
            return;
        }

        if ((float) $value > (float) $max) {
            $fail(sprintf(
                'الحد الأقصى لهذا النوع من الإجازات (%s) هو %d يوم في الطلب الواحد.',
                $this->leaveType->name,
                $max
            ));
        }
    }
}
