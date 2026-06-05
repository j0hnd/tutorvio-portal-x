<?php

namespace App\Notifications;

use App\Models\Notification as PortalNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SystemNotificationEmail extends Notification
{
    use Queueable;

    public function __construct(private readonly PortalNotification $notification) {}

    /**
     * Select the delivery channels for a stored portal notification email.
     *
     * Laravel calls this when `SystemNotificationService` dispatches an email
     * copy of an already-created portal notification. The method only declares
     * the mail channel; portal recipient rows, statuses, duplicate checks, and
     * failure logs are handled by the service before and after notification
     * delivery.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the email copy of a portal notification.
     *
     * Laravel calls this during mail delivery. It reads the stored notification
     * title, body, and optional action metadata, but does not update statuses or
     * write logs itself. Retry safety is controlled by the service's email
     * delivery row, which skips recipients already marked sent or delivered.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->notification->title)
            ->greeting('Hi '.$notifiable->name.',');

        if ($this->notification->body) {
            $message->line($this->notification->body);
        }

        $metadata = $this->notification->metadata ?? [];

        if (isset($metadata['action_text'], $metadata['action_url'])) {
            $message->action($metadata['action_text'], $metadata['action_url']);
        }

        return $message;
    }
}
