<?php

namespace App\Enums;

enum StepStatus: string
{
    case PENDING  = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case RETURNED = 'returned';
    case SKIPPED  = 'skipped';

    public function label(): string
    {
        return match($this) {
            self::PENDING  => 'في الانتظار',
            self::APPROVED => 'معتمد',
            self::REJECTED => 'مرفوض',
            self::RETURNED => 'مُعاد للتعديل',
            self::SKIPPED  => 'متخطى',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::PENDING  => 'warning',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
            self::RETURNED => 'warning',
            self::SKIPPED  => 'gray',
        };
    }

    public function isDecision(): bool
    {
        return in_array($this, [self::APPROVED, self::REJECTED, self::RETURNED]);
    }
}
