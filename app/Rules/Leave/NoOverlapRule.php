<?php

namespace App\Rules\Leave;

use App\Models\LeaveRequest;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects a leave request if the employee already has an active (non-rejected,
 * non-cancelled) request that overlaps the requested date range.
 */
class NoOverlapRule implements ValidationRule, DataAwareRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function __construct(
        private readonly int $employeeId,
        private readonly ?int $excludeRequestId = null,
    ) {}

    public function setData(array $data): static
    {
        $this->data = $data;
        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $start = $this->data['start_date'] ?? null;
        $end   = $this->data['end_date']   ?? null;

        if (! $start || ! $end) {
            return;
        }

        $exists = LeaveRequest::withoutGlobalScopes()
            ->overlapping($this->employeeId, $start, $end, $this->excludeRequestId)
            ->exists();

        if ($exists) {
            $fail('يوجد طلب إجازة آخر يتداخل مع هذه الفترة.');
        }
    }
}
