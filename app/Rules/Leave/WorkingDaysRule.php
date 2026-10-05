<?php

namespace App\Rules\Leave;

use App\Models\Holiday;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Calculates working days in a date range, excluding:
 *  - Weekly off days (Friday=5, Saturday=6 by default, configurable)
 *  - Official holidays from the `holidays` table
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

        $offDays = config('leave.working_week_off_days', [5, 6]);

        // Fetch holidays in range as a Set for O(1) lookup
        $holidays = Holiday::whereBetween('date', [
            $start->toDateString(),
            $end->toDateString(),
        ])->pluck('date')->map(fn($d) => Carbon::parse($d)->toDateString())->flip()->all();

        $count = 0;
        foreach (CarbonPeriod::create($start, $end) as $day) {
            /** @var Carbon $day */
            if (in_array($day->dayOfWeek, $offDays)) {
                continue;
            }
            if (isset($holidays[$day->toDateString()])) {
                continue;
            }
            $count++;
        }

        $this->calculatedDays = max(0, $count);
    }
}
