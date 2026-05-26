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
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

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
