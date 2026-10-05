<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Unified notification for all leave request lifecycle events.
 *
 * Events: submitted | approved | rejected | returned | cancelled
 *
 * Delivered via:
 *  - 'database' always
 *  - 'mail' when config('leave.email_notifications') = true
 */
class LeaveRequestNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly LeaveRequest $leaveRequest,
        public readonly string $event,
        public readonly string $message,
    ) {}

    // -------------------------------------------------------------------------
    // Channels
    // -------------------------------------------------------------------------

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (config('leave.email_notifications', false)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    // -------------------------------------------------------------------------
    // Database payload
    // -------------------------------------------------------------------------

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'leave_request_id' => $this->leaveRequest->id,
            'number'           => $this->leaveRequest->number,
            'event'            => $this->event,
            'message'          => $this->message,
            'employee_name'    => $this->leaveRequest->employee?->full_name,
            'organization'     => $this->leaveRequest->organization?->name,
            'status'           => $this->leaveRequest->status->label(),
            'days'             => $this->leaveRequest->days,
            'start_date'       => $this->leaveRequest->start_date?->toDateString(),
            'end_date'         => $this->leaveRequest->end_date?->toDateString(),
        ];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    // -------------------------------------------------------------------------
    // Email payload
    // -------------------------------------------------------------------------

    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->emailSubject();

        return (new MailMessage())
            ->subject($subject)
            ->greeting("مرحباً {$notifiable->name},")
            ->line($this->message)
            ->line("رقم الطلب: {$this->leaveRequest->number}")
            ->line("الموظف: " . ($this->leaveRequest->employee?->full_name ?? '—'))
            ->line("الفترة: {$this->leaveRequest->start_date?->toDateString()} — {$this->leaveRequest->end_date?->toDateString()}")
            ->line("عدد الأيام: {$this->leaveRequest->days}")
            ->when(
                $this->event === 'rejected' && $this->leaveRequest->rejection_reason,
                fn($mail) => $mail->line("سبب الرفض: {$this->leaveRequest->rejection_reason}")
            );
    }

    private function emailSubject(): string
    {
        return match($this->event) {
            'submitted' => "طلب إجازة جديد — {$this->leaveRequest->number}",
            'approved'  => "تمت الموافقة على طلب الإجازة — {$this->leaveRequest->number}",
            'rejected'  => "تم رفض طلب الإجازة — {$this->leaveRequest->number}",
            'returned'  => "طلب إجازة مُعاد للتعديل — {$this->leaveRequest->number}",
            'cancelled' => "تم إلغاء طلب الإجازة — {$this->leaveRequest->number}",
            'overdue'   => "طلب إجازة متأخر يحتاج إجراءً — {$this->leaveRequest->number}",
            default     => "إشعار طلب إجازة — {$this->leaveRequest->number}",
        };
    }
}
