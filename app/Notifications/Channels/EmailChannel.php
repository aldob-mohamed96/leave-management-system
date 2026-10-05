<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Notifications\LeaveRequestNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends notifications via email.
 *
 * Controlled by config('leave.email_notifications', false).
 * When disabled (default), logs to the log channel so tests don't fail
 * due to missing mail configuration.
 *
 * To enable in production:
 *   Set LEAVE_EMAIL_NOTIFICATIONS=true in .env
 *   Configure MAIL_* settings normally.
 */
class EmailChannel implements NotificationChannelInterface
{
    public function send(User $notifiable, LeaveRequestNotification $notification): void
    {
        if (! config('leave.email_notifications', false)) {
            Log::info('[EmailChannel] Email notifications disabled. Would have sent to: ' . $notifiable->email, [
                'event'            => $notification->event,
                'leave_request_id' => $notification->leaveRequest->id,
                'number'           => $notification->leaveRequest->number,
            ]);
            return;
        }

        try {
            $notifiable->notify($notification);
        } catch (\Throwable $e) {
            Log::error('[EmailChannel] Failed to send email notification.', [
                'user_id' => $notifiable->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
