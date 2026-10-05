<?php

namespace App\Enums;

enum ApprovalRule: string
{
    case ALL      = 'all';      // كل المعتمدين يجب أن يوافقوا
    case ANY      = 'any';      // أي واحد يوافق يكفي
    case MAJORITY = 'majority'; // الأغلبية تكفي (أكثر من النصف)

    public function label(): string
    {
        return match($this) {
            self::ALL      => 'الجميع',
            self::ANY      => 'أي معتمد',
            self::MAJORITY => 'الأغلبية',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::ALL      => 'يجب أن يوافق جميع المعتمدين للانتقال للمرحلة التالية',
            self::ANY      => 'يكفي موافقة أي معتمد واحد للانتقال للمرحلة التالية',
            self::MAJORITY => 'يجب أن توافق أغلبية المعتمدين (أكثر من النصف) للانتقال',
        };
    }

    /**
     * Determine if the stage is approved based on counts.
     */
    public function isStageApproved(int $approvedCount, int $totalApprovers): bool
    {
        return match($this) {
            self::ALL      => $approvedCount >= $totalApprovers && $totalApprovers > 0,
            self::ANY      => $approvedCount >= 1,
            self::MAJORITY => $totalApprovers > 0 && $approvedCount > ($totalApprovers / 2),
        };
    }
}
