<?php

namespace App\Enums;

enum TransactionType: string
{
    case ACCRUAL    = 'accrual';    // استحقاق
    case DEDUCTION  = 'deduction';  // خصم
    case REFUND     = 'refund';     // استرداد
    case CARRYOVER  = 'carryover';  // ترحيل من السنة السابقة
    case ADJUSTMENT = 'adjustment'; // تعديل يدوي

    public function label(): string
    {
        return match($this) {
            self::ACCRUAL    => 'استحقاق',
            self::DEDUCTION  => 'خصم',
            self::REFUND     => 'استرداد',
            self::CARRYOVER  => 'ترحيل',
            self::ADJUSTMENT => 'تعديل يدوي',
        };
    }

    /** Positive types increase balance, negative types decrease it */
    public function isCredit(): bool
    {
        return in_array($this, [self::ACCRUAL, self::REFUND, self::CARRYOVER]);
    }

    public function isDebit(): bool
    {
        return $this === self::DEDUCTION;
    }
}
