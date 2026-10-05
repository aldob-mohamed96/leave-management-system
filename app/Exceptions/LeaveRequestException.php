<?php

namespace App\Exceptions;

use App\Enums\LeaveStatus;

class LeaveRequestException extends \RuntimeException
{
    /**
     * Factory: invalid status transition.
     */
    public static function invalidTransition(
        LeaveStatus $from,
        LeaveStatus $to,
        ?string $detail = null
    ): self {
        $message = "لا يمكن الانتقال من {$from->label()} إلى {$to->label()}";

        if ($detail !== null) {
            $message .= " — {$detail}";
        }

        return new self($message);
    }

    /**
     * Factory: no workflow configuration found for an organization.
     */
    public static function noWorkflowConfigured(int $organizationId): self
    {
        return new self("لم يتم إعداد مسار الاعتماد للمؤسسة رقم {$organizationId}. يرجى مراجعة إعدادات سير العمل.");
    }

    /**
     * Factory: insufficient leave balance.
     */
    public static function insufficientBalance(
        float $remaining,
        float $requested,
        ?string $detail = null
    ): self {
        $message = sprintf(
            'الرصيد المتبقي (%.1f يوم) أقل من المطلوب (%.1f يوم)',
            $remaining,
            $requested
        );
        if ($detail !== null) {
            $message .= " — {$detail}";
        }
        return new self($message);
    }
}
