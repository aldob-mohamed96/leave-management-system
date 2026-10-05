<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Notifications\LeaveRequestNotification;

/**
 * Stores notifications in the database via Laravel's built-in
 * notifications table (requires the `notifications` table migration).
 */
class DatabaseChannel implements NotificationChannelInterface
{
    public function send(User $notifiable, LeaveRequestNotification $notification): void
    {
        $notifiable->notify($notification);
    }
}
