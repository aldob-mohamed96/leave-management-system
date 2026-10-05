<?php

namespace App\Services\DTO;

use App\Models\LeaveRequest;

/**
 * Returned by LeaveRequestService::create().
 * Carries the newly-created draft request plus any non-blocking warnings
 * (e.g. insufficient balance in warn-only mode, casual-before-regular hint).
 */
readonly class CreateLeaveRequestResult
{
    public function __construct(
        public readonly LeaveRequest $request,
        public readonly array $warnings,
    ) {}

    public function hasWarnings(): bool
    {
        return ! empty($this->warnings);
    }
}
