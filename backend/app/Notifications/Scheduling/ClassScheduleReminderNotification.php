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
     * Select the delivery channels for a class reminder.
     *
     * Laravel calls this when `ScheduleReminderService` sends a due reminder.
     * The notification is mail-only; the service creates the companion portal
     * notification, records email delivery status, cancels ineligible reminders,
     * and logs delivery failures.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the class reminder email.
     *
     * Laravel calls this during mail delivery for a claimed pending reminder.
     * The method reads the reminder's schedule, recipient timezone, counterparty,
     * and meeting link. It does not write logs or update reminder status itself.
     * Retry safety is handled by the service claiming pending reminders before
     * sending and moving them to sent, failed, or cancelled afterward.
     */
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
