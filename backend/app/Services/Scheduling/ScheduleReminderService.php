<?php

namespace App\Services\Scheduling;

use App\Models\Scheduling\ClassSchedule;
use App\Models\Scheduling\ScheduleReminder;
use App\Models\User;
use App\Notifications\Scheduling\ClassScheduleReminderNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Throwable;

class ScheduleReminderService
{
    /**
     * @var array<int, int>
     */
    private const DEFAULT_REMINDER_OFFSETS_MINUTES = [1440, 60];

    /**
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

                    $sent++;
                } catch (Throwable $exception) {
                    $reminder->forceFill([
                        'status' => ScheduleReminder::STATUS_FAILED,
                        'metadata' => [
                            ...($reminder->metadata ?? []),
                            'error' => $exception->getMessage(),
                        ],
                    ])->save();
                }
            });

        return $sent;
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
