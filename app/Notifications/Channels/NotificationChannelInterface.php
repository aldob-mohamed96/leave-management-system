<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Notifications\LeaveRequestNotification;

/**
 * Contract for notification delivery channels.
 *
 * Implement this interface to add new channels (e.g. WhatsApp, SMS)
 * without changing existing notification code.
 *
 * Usage:
 *   class WhatsAppChannel implements NotificationChannelInterface {
 *       public function send(User $notifiable, LeaveRequestNotification $notification): void {
 *           // send via WhatsApp API
 *       }
 *   }
 */
interface NotificationChannelInterface
{
    public function send(User $notifiable, LeaveRequestNotification $notification): void;
}
