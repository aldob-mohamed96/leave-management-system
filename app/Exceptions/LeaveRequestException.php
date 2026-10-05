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
     * Factory: insufficient leave balance.
     */
    public static function insufficientBalance(
        float $remaining,
        float $requested,
        ?string $detail = null
    ): self {
        $message = "الرصيد المتبقي ({$remaining} يوم) أقل من المطلوب ({$requested} يوم)";

        if ($detail !== null) {
            $message .= " — {$detail}";
        }

        return new self($message);
    }
}
