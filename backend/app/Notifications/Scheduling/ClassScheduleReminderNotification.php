<?php

namespace App\Notifications\Scheduling;

use App\Models\Scheduling\ScheduleReminder;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClassScheduleReminderNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly ScheduleReminder $reminder) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $schedule = $this->reminder->classSchedule;
        $timezone = $notifiable->timezone ?: $schedule->timezone;
        $startsAt = CarbonImmutable::parse($schedule->starts_at)->setTimezone($timezone);
        $endsAt = CarbonImmutable::parse($schedule->ends_at)->setTimezone($timezone);
        $counterparty = (int) $notifiable->id === (int) $schedule->student_id
            ? $schedule->teacher?->name
            : $schedule->student?->name;

        $message = (new MailMessage)
            ->subject('Class reminder: '.$this->title())
            ->greeting('Hi '.$notifiable->name.',')
            ->line('This is a reminder for your upcoming class.')
            ->line('Class: '.$this->title())
            ->line('Time: '.$startsAt->format('M j, Y g:i A').' - '.$endsAt->format('g:i A').' '.$startsAt->format('T'));

        if ($counterparty) {
            $message->line('With: '.$counterparty);
        }

        if ($schedule->meeting_url) {
            $message->action('Join class', $schedule->meeting_url);
        }

        return $message;
    }

    private function title(): string
    {
        return $this->reminder->classSchedule->title ?: 'Scheduled class';
    }
}
