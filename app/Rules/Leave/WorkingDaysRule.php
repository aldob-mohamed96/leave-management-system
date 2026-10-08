<?php

namespace App\Rules\Leave;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Calculates the total number of calendar days in a date range (inclusive),
 * counting ALL days regardless of weekends or official holidays.
 *
 * Does NOT call $fail — instead exposes $calculatedDays for the service
 * to overwrite the submitted `days` value.
 */
class WorkingDaysRule implements ValidationRule, DataAwareRule
{
    public int $calculatedDays = 0;

    /** @var array<string, mixed> */
    private array $data = [];

    public function setData(array $data): static
    {
        $this->data = $data;
        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $start = isset($this->data['start_date'])
            ? Carbon::parse($this->data['start_date'])->startOfDay()
            : null;

        $end = isset($this->data['end_date'])
            ? Carbon::parse($this->data['end_date'])->startOfDay()
            : null;

        if (! $start || ! $end || $end->lt($start)) {
            // Let other rules handle invalid date ranges
            $this->calculatedDays = max(1, (int) $value);
            return;
        }

        // Count all calendar days inclusive (no exclusions for weekends or holidays)
        $this->calculatedDays = (int) $start->diffInDays($end) + 1;
    }
}
