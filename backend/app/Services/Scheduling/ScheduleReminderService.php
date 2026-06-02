<?php

namespace App\Services\Scheduling;

use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Models\Scheduling\ClassSchedule;
use App\Models\Scheduling\ScheduleReminder;
use App\Models\User;
use App\Notifications\Scheduling\ClassScheduleReminderNotification;
use App\Services\Notifications\SystemNotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class ScheduleReminderService
{
    /**
     * @var array<int, int>
     */
    private const DEFAULT_REMINDER_OFFSETS_MINUTES = [1440, 60];

    public function __construct(private readonly SystemNotificationService $notificationService) {}

    /**
     * Create a schedule reminder row.
     *
     * The payload is expected to be validated upstream. The reminder send time
     * is parsed in the supplied timezone and stored in UTC.
     *
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload): ScheduleReminder
    {
        return ScheduleReminder::create([
            ...Arr::only($payload, ['class_schedule_id', 'user_id', 'channel', 'status', 'metadata']),
            'scheduled_for' => CarbonImmutable::parse($payload['scheduled_for'], $payload['timezone'] ?? config('app.timezone'))->utc(),
        ]);
    }

    /**
     * Update a schedule reminder row.
     *
     * Mutable reminder fields are filled from the payload, and scheduled time is
     * re-parsed to UTC when present.
     *
     * @param  array<string, mixed>  $payload
     */
    public function update(ScheduleReminder $reminder, array $payload): ScheduleReminder
    {
        $reminder->fill(Arr::only($payload, ['class_schedule_id', 'user_id', 'channel', 'status', 'sent_at', 'metadata']));

        if (array_key_exists('scheduled_for', $payload)) {
            $reminder->scheduled_for = CarbonImmutable::parse($payload['scheduled_for'], $payload['timezone'] ?? config('app.timezone'))->utc();
        }

        $reminder->save();

        return $reminder->refresh();
    }

    /**
     * Queue reminder rows for upcoming scheduled classes.
     *
     * The method scans scheduled classes within the lookahead window and creates
     * missing email reminders for eligible student and teacher participants.
     */
    public function queueUpcoming(int $lookaheadHours = 48, ?CarbonImmutable $now = null): int
    {
        $now = ($now ?? CarbonImmutable::now('UTC'))->utc();
        $until = $now->addHours($lookaheadHours);
        $queued = 0;

        ClassSchedule::query()
            ->with(['student:id,name,email,timezone', 'teacher:id,name,email,timezone'])
            ->where('status', ClassSchedule::STATUS_SCHEDULED)
            ->where('starts_at', '>', $now)
            ->where('starts_at', '<=', $until)
            ->orderBy('id')
            ->chunkById(100, function (Collection $schedules) use ($now, &$queued): void {
                $schedules->each(function (ClassSchedule $schedule) use ($now, &$queued): void {
                    $queued += $this->queueForSchedule($schedule, $now);
                });
            });

        return $queued;
    }

    /**
     * Queue reminder rows for one class schedule.
     *
     * The schedule is skipped when it is no longer scheduled or starts in the
     * past. New reminder rows are created for participants with email addresses
     * at the configured offsets.
     */
    public function queueForSchedule(ClassSchedule $schedule, ?CarbonImmutable $now = null): int
    {
        $now = ($now ?? CarbonImmutable::now('UTC'))->utc();
        $schedule->loadMissing(['student:id,name,email,timezone', 'teacher:id,name,email,timezone']);

        if ($schedule->status !== ClassSchedule::STATUS_SCHEDULED || $schedule->starts_at->utc()->lessThanOrEqualTo($now)) {
            return 0;
        }

        $queued = 0;

        foreach ($this->participants($schedule) as $participant) {
            if (! $participant->email) {
                continue;
            }

            foreach (self::DEFAULT_REMINDER_OFFSETS_MINUTES as $offsetMinutes) {
                $scheduledFor = $schedule->starts_at->subMinutes($offsetMinutes)->utc();

                $reminder = ScheduleReminder::firstOrCreate([
                    'class_schedule_id' => $schedule->id,
                    'user_id' => $participant->id,
                    'channel' => 'email',
                    'scheduled_for' => $scheduledFor,
                ], [
                    'status' => ScheduleReminder::STATUS_PENDING,
                    'metadata' => [
                        'offset_minutes' => $offsetMinutes,
                        'class_timezone' => $schedule->timezone,
                        'recipient_timezone' => $participant->timezone ?: $schedule->timezone,
                    ],
                ]);

                if ($reminder->wasRecentlyCreated) {
                    $queued++;
                }
            }
        }

        return $queued;
    }

    /**
     * Send due schedule reminder emails.
     *
     * Pending reminders are claimed, portal notifications are attempted, email
     * delivery is recorded, and ineligible reminders are cancelled.
     */
    public function sendDue(?CarbonImmutable $now = null, int $limit = 100): int
    {
        $now = ($now ?? CarbonImmutable::now('UTC'))->utc();
        $sent = 0;

        ScheduleReminder::query()
            ->with(['classSchedule.student:id,name,email,timezone', 'classSchedule.teacher:id,name,email,timezone', 'user:id,name,email,timezone'])
            ->where('channel', 'email')
            ->where('status', ScheduleReminder::STATUS_PENDING)
            ->where('scheduled_for', '<=', $now)
            ->orderBy('scheduled_for')
            ->limit($limit)
            ->get()
            ->each(function (ScheduleReminder $reminder) use ($now, &$sent): void {
                $claimed = ScheduleReminder::query()
                    ->whereKey($reminder->id)
                    ->where('status', ScheduleReminder::STATUS_PENDING)
                    ->update(['status' => ScheduleReminder::STATUS_SENDING]);

                if ($claimed === 0) {
                    return;
                }

                $reminder->refresh();

                if (! $this->canSend($reminder, $now)) {
                    $this->cancelReminder($reminder, 'Class is no longer eligible for reminders.');

                    return;
                }

                $portalNotification = null;

                try {
                    $portalNotification = $this->createPortalReminder($reminder, $now);
                } catch (Throwable $exception) {
                    Log::warning('Class reminder portal notification creation failed.', [
                        'schedule_reminder_id' => $reminder->id,
                        'class_schedule_id' => $reminder->class_schedule_id,
                        'user_id' => $reminder->user_id,
                        'failure_type' => $exception::class,
                    ]);
                }

                try {
                    $reminder->user->notify(new ClassScheduleReminderNotification($reminder));

                    $reminder->forceFill([
                        'status' => ScheduleReminder::STATUS_SENT,
                        'sent_at' => $now,
                        'metadata' => [
                            ...($reminder->metadata ?? []),
                            'sent_timezone' => $reminder->user->timezone ?: $reminder->classSchedule->timezone,
                        ],
                    ])->save();

                    $this->recordEmailDelivery($portalNotification, $reminder, true);

                    $sent++;
                } catch (Throwable $exception) {
                    $reminder->forceFill([
                        'status' => ScheduleReminder::STATUS_FAILED,
                        'metadata' => [
                            ...($reminder->metadata ?? []),
                            'failure_type' => $exception::class,
                        ],
                    ])->save();

                    $this->recordEmailDelivery($portalNotification, $reminder, false, $exception);

                    Log::warning('Class reminder email delivery failed.', [
                        'schedule_reminder_id' => $reminder->id,
                        'class_schedule_id' => $reminder->class_schedule_id,
                        'user_id' => $reminder->user_id,
                        'failure_type' => $exception::class,
                    ]);
                }
            });

        return $sent;
    }

    private function createPortalReminder(ScheduleReminder $reminder, CarbonImmutable $now): Notification
    {
        $schedule = $reminder->classSchedule;
        $offsetMinutes = (int) data_get($reminder->metadata, 'offset_minutes', $schedule->starts_at->diffInMinutes($now));
        $title = $schedule->title ?: 'Scheduled class';

        return $this->notificationService->classReminder(
            $reminder->user,
            'Class reminder: '.$title,
            'Your class starts '.$this->startsAtLabel($schedule, $reminder->user).'.',
            [
                'class_schedule_id' => $schedule->id,
                'schedule_reminder_id' => $reminder->id,
                'offset_minutes' => $offsetMinutes,
            ],
            [
                'published_at' => $now,
                'source_type' => 'schedule_reminder',
                'source_id' => $reminder->id,
            ]
        );
    }

    private function recordEmailDelivery(?Notification $notification, ScheduleReminder $reminder, bool $sent, ?Throwable $exception = null): void
    {
        if ($notification === null) {
            return;
        }

        NotificationRecipient::updateOrCreate([
            'notification_id' => $notification->id,
            'user_id' => $reminder->user_id,
            'channel' => NotificationRecipient::CHANNEL_EMAIL,
        ], [
            'delivery_status' => $sent
                ? NotificationRecipient::STATUS_SENT
                : NotificationRecipient::STATUS_FAILED,
            'sent_at' => $sent ? ($reminder->sent_at ?? now()) : null,
            'metadata' => [
                ...($reminder->metadata ?? []),
                'schedule_reminder_id' => $reminder->id,
                ...($exception === null ? [] : ['failure_type' => $exception::class]),
            ],
        ]);
    }

    private function startsAtLabel(ClassSchedule $schedule, User $recipient): string
    {
        $timezone = $recipient->timezone ?: $schedule->timezone;

        return $schedule->starts_at
            ->setTimezone($timezone)
            ->format('M j, Y g:i A T');
    }

    private function canSend(ScheduleReminder $reminder, CarbonImmutable $now): bool
    {
        $schedule = $reminder->classSchedule;

        return $schedule !== null
            && $reminder->user !== null
            && (bool) $reminder->user->email
            && $schedule->status === ClassSchedule::STATUS_SCHEDULED
            && $schedule->starts_at->utc()->greaterThan($now);
    }

    private function cancelReminder(ScheduleReminder $reminder, string $reason): void
    {
        $reminder->forceFill([
            'status' => ScheduleReminder::STATUS_CANCELLED,
            'metadata' => [
                ...($reminder->metadata ?? []),
                'cancelled_reason' => $reason,
            ],
        ])->save();
    }

    /**
     * @return Collection<int, User>
     */
    private function participants(ClassSchedule $schedule): Collection
    {
        return collect([$schedule->student, $schedule->teacher])
            ->filter()
            ->unique('id')
            ->values();
    }
}
