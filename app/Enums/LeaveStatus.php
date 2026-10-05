<?php

namespace App\Enums;

enum LeaveStatus: string
{
    case DRAFT     = 'draft';
    case SUBMITTED = 'submitted';
    case IN_REVIEW = 'in_review';
    case RETURNED  = 'returned';
    case APPROVED  = 'approved';
    case REJECTED  = 'rejected';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::DRAFT     => 'مسودة',
            self::SUBMITTED => 'مقدّم',
            self::IN_REVIEW => 'قيد المراجعة',
            self::RETURNED  => 'مُعاد للتعديل',
            self::APPROVED  => 'معتمد',
            self::REJECTED  => 'مرفوض',
            self::CANCELLED => 'ملغي',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::DRAFT     => 'gray',
            self::SUBMITTED => 'info',
            self::IN_REVIEW => 'warning',
            self::RETURNED  => 'warning',
            self::APPROVED  => 'success',
            self::REJECTED  => 'danger',
            self::CANCELLED => 'gray',
        };
    }

    /** Terminal states — no further transitions possible */
    public function isTerminal(): bool
    {
        return in_array($this, [self::APPROVED, self::REJECTED, self::CANCELLED]);
    }

    /** States that allow the request to be cancelled */
    public function canBeCancelled(): bool
    {
        return in_array($this, [self::DRAFT, self::SUBMITTED, self::IN_REVIEW, self::RETURNED]);
    }

    /** States that allow the request to be edited */
    public function canBeEdited(): bool
    {
        return in_array($this, [self::DRAFT, self::RETURNED]);
    }
}
