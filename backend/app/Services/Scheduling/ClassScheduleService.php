<?php

namespace App\Services\Scheduling;

use App\Enums\AuditActionType;
use App\Enums\AuditModule;
use App\Models\Scheduling\ClassSchedule;
use App\Models\Scheduling\TeacherAvailability;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\Notifications\SystemNotificationService;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class ClassScheduleService
{
    public function __construct(
        private readonly SchedulingAvailabilityService $availabilityService,
        private readonly SystemNotificationService $notificationService,
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * Create a scheduled class for a student and teacher.
     *
     * The payload is expected to be validated before this service is called.
     * The method verifies student role and teacher availability, calculates the
     * teacher booking block, creates the schedule, and records an audit log.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    public function create(array $payload, User $actor): ClassSchedule
    {
        [$startsAtUtc, $endsAtUtc] = $this->utcRange($payload['starts_at'], $payload['ends_at'], $payload['timezone']);
        $teacher = User::findOrFail($payload['teacher_id']);
        $student = User::findOrFail($payload['student_id']);
        $classType = $this->classType($payload, $student);
        $teacherBlockedUntilUtc = $this->teacherBlockedUntil($classType, $startsAtUtc, $endsAtUtc, $payload['timezone']);

        if (! $student->hasRole('student')) {
            throw ValidationException::withMessages([
                'student_id' => 'The selected user must have the student role.',
            ]);
        }

        $this->availabilityService->assertTeacherCanBeBooked(
            $teacher,
            $startsAtUtc,
            $endsAtUtc,
            $payload['timezone'],
            teacherBlockedUntilUtc: $teacherBlockedUntilUtc
        );

        $schedule = ClassSchedule::create([
            ...Arr::only($payload, ['student_id', 'teacher_id', 'title', 'description', 'timezone', 'meeting_url', 'notes', 'rescheduled_from_id']),
            'status' => $payload['status'] ?? ClassSchedule::STATUS_SCHEDULED,
            'class_type' => $classType,
            'starts_at' => $startsAtUtc,
            'ends_at' => $endsAtUtc,
            'teacher_blocked_until' => $teacherBlockedUntilUtc,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        $this->logLessonCreated($schedule, $actor);

        return $schedule;
    }

    /**
     * Create a series of recurring class schedules.
     *
     * The payload supplies the recurrence pattern and class details. Eligible
     * occurrences are created in a transaction and audited, while occurrences
     * that fail availability validation are returned in the skipped list.
     *
     * @param  array<string, mixed>  $payload
     * @return array{created: array<int, ClassSchedule>, skipped: array<int, array<string, string>>, requested_occurrences: int}
     *
     * @throws ValidationException
     */
    public function createRecurring(array $payload, User $actor): array
    {
        $teacher = User::findOrFail($payload['teacher_id']);
        $student = User::findOrFail($payload['student_id']);

        if (! $student->hasRole('student')) {
            throw ValidationException::withMessages([
                'student_id' => 'The selected user must have the student role.',
            ]);
        }

        if (! $teacher->hasRole('teacher')) {
            throw ValidationException::withMessages([
                'teacher_id' => 'The selected user must have the teacher role.',
            ]);
        }

        $occurrences = $this->recurringOccurrences($payload);
        $classType = $this->classType($payload, $student);
        $created = [];
        $skipped = [];

        DB::transaction(function () use ($payload, $actor, $teacher, $student, $occurrences, $classType, &$created, &$skipped): void {
            foreach ($occurrences as $occurrence) {
                $teacherBlockedUntilUtc = $this->teacherBlockedUntil(
                    $classType,
                    $occurrence['starts_at_utc'],
                    $occurrence['ends_at_utc'],
                    $payload['timezone']
                );

                try {
                    $this->availabilityService->assertTeacherCanBeBooked(
                        $teacher,
                        $occurrence['starts_at_utc'],
                        $occurrence['ends_at_utc'],
                        $payload['timezone'],
                        student: $student,
                        teacherBlockedUntilUtc: $teacherBlockedUntilUtc
                    );

                    $schedule = ClassSchedule::create([
                        ...Arr::only($payload, ['student_id', 'teacher_id', 'title', 'description', 'timezone', 'meeting_url', 'notes']),
                        'status' => $payload['status'] ?? ClassSchedule::STATUS_SCHEDULED,
                        'class_type' => $classType,
                        'starts_at' => $occurrence['starts_at_utc'],
                        'ends_at' => $occurrence['ends_at_utc'],
                        'teacher_blocked_until' => $teacherBlockedUntilUtc,
                        'created_by' => $actor->id,
                        'updated_by' => $actor->id,
                    ]);
                    $this->logLessonCreated($schedule, $actor);
                    $created[] = $schedule;
                } catch (ValidationException $exception) {
                    $skipped[] = [
                        'date' => $occurrence['date'],
                        'starts_at' => $occurrence['starts_at_local']->toIso8601String(),
                        'ends_at' => $occurrence['ends_at_local']->toIso8601String(),
                        'reason' => $this->validationReason($exception),
                    ];
                }
            }
        });

        return [
            'created' => $created,
            'skipped' => $skipped,
            'requested_occurrences' => $occurrences->count(),
        ];
    }

    /**
     * Book a one-time lesson for a student with their assigned teacher.
     *
     * The method verifies the actor is a student, enforces assigned-teacher
     * booking, acquires configured cache locks for the student attempt and
     * teacher availability day, checks availability, creates the schedule, and
     * records the creation audit entry.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    public function bookOneTimeLesson(array $payload, User $student): ClassSchedule
    {
        if (! $student->hasRole('student')) {
            throw ValidationException::withMessages([
                'student_id' => 'Only students can book lessons.',
            ]);
        }

        $assignedTeacherId = $student->studentProfile?->assigned_teacher_id;

        if (! $assignedTeacherId) {
            throw ValidationException::withMessages([
                'teacher_id' => 'The student does not have an assigned teacher.',
            ]);
        }

        if ((int) $payload['teacher_id'] !== (int) $assignedTeacherId) {
            throw ValidationException::withMessages([
                'teacher_id' => 'Students can only book lessons with their assigned teacher.',
            ]);
        }

        [$startsAtUtc, $endsAtUtc] = $this->utcRange($payload['starts_at'], $payload['ends_at'], $payload['timezone']);
        $teacher = User::findOrFail($payload['teacher_id']);
        $classType = $this->classType($payload, $student);
        $teacherBlockedUntilUtc = $this->teacherBlockedUntil($classType, $startsAtUtc, $endsAtUtc, $payload['timezone']);

        return $this->withLessonBookingLocks(
            $student,
            $teacher,
            $startsAtUtc,
            $endsAtUtc,
            $teacherBlockedUntilUtc,
            $payload['timezone'],
            function () use ($payload, $student, $teacher, $startsAtUtc, $endsAtUtc, $classType, $teacherBlockedUntilUtc): ClassSchedule {
                return DB::transaction(function () use ($payload, $student, $teacher, $startsAtUtc, $endsAtUtc, $classType, $teacherBlockedUntilUtc): ClassSchedule {
                    $this->availabilityService->assertTeacherCanBeBooked(
                        $teacher,
                        $startsAtUtc,
                        $endsAtUtc,
                        $payload['timezone'],
                        student: $student,
                        teacherBlockedUntilUtc: $teacherBlockedUntilUtc
                    );

                    $schedule = ClassSchedule::create([
                        ...Arr::only($payload, ['teacher_id', 'title', 'description', 'timezone', 'meeting_url', 'notes']),
                        'student_id' => $student->id,
                        'status' => $payload['status'] ?? ClassSchedule::STATUS_PENDING_CONFIRMATION,
                        'class_type' => $classType,
                        'starts_at' => $startsAtUtc,
                        'ends_at' => $endsAtUtc,
                        'teacher_blocked_until' => $teacherBlockedUntilUtc,
                        'created_by' => $student->id,
                        'updated_by' => $student->id,
                    ]);

                    $this->logLessonCreated($schedule, $student);

                    return $schedule;
                });
            }
        );
    }

    /**
     * Serialize student-initiated bookings around the student attempt and the
     * teacher availability day to close the gap between validation and insert.
     * The cache store is configurable so tests or non-Redis environments can
     * use another lock-capable store while local production-like paths use
     * Redis.
     */
    private function withLessonBookingLocks(
        User $student,
        User $teacher,
        CarbonImmutable $startsAtUtc,
        CarbonImmutable $endsAtUtc,
        CarbonImmutable $teacherBlockedUntilUtc,
        string $timezone,
        Closure $callback
    ): ClassSchedule {
        $locks = [];

        try {
            $locks[] = $this->acquireLessonBookingLock(
                $this->studentBookingLockKey($student, $startsAtUtc, $endsAtUtc),
                'starts_at',
                'A booking attempt is already in progress for this student and time slot.'
            );

            $locks[] = $this->acquireLessonBookingLock(
                $this->teacherAvailabilitySlotLockKey($teacher, $startsAtUtc, $teacherBlockedUntilUtc, $timezone),
                'starts_at',
                'This lesson slot is already being booked. Please try another time or retry shortly.'
            );

            return $callback();
        } finally {
            foreach (array_reverse($locks) as $lock) {
                try {
                    $lock->release();
                } catch (Throwable $exception) {
                    Log::warning('Lesson booking lock release failed.', [
                        'failure_type' => $exception::class,
                    ]);
                }
            }
        }
    }

    /**
     * Acquire a fail-fast lesson-booking lock from the configured cache store.
     *
     * Lock contention is returned as validation feedback instead of waiting or
     * surfacing a generic server error.
     */
    private function acquireLessonBookingLock(string $key, string $field, string $message): Lock
    {
        $lock = Cache::store($this->bookingLockStore())->lock($key, $this->bookingLockTtlSeconds());

        if (! $lock->get()) {
            throw ValidationException::withMessages([
                $field => $message,
            ]);
        }

        return $lock;
    }

    private function studentBookingLockKey(User $student, CarbonImmutable $startsAtUtc, CarbonImmutable $endsAtUtc): string
    {
        return implode(':', [
            'tvio',
            'lesson_booking',
            'student',
            $student->id,
            $startsAtUtc->timestamp,
            $endsAtUtc->timestamp,
        ]);
    }

    private function teacherAvailabilitySlotLockKey(
        User $teacher,
        CarbonImmutable $startsAtUtc,
        CarbonImmutable $teacherBlockedUntilUtc,
        string $timezone
    ): string {
        $localStart = $startsAtUtc->setTimezone($timezone);
        $localEnd = $teacherBlockedUntilUtc->setTimezone($timezone);

        $availabilityId = TeacherAvailability::query()
            ->where('teacher_id', $teacher->id)
            ->where('is_active', true)
            ->where('day_of_week', $localStart->dayOfWeek)
            ->where('timezone', $timezone)
            ->whereTime('start_time', '<=', $localStart->format('H:i:s'))
            ->whereTime('end_time', '>=', $localEnd->format('H:i:s'))
            ->where(function ($query) use ($localStart) {
                $query->whereNull('effective_from')
                    ->orWhereDate('effective_from', '<=', $localStart->toDateString());
            })
            ->where(function ($query) use ($localStart) {
                $query->whereNull('effective_until')
                    ->orWhereDate('effective_until', '>=', $localStart->toDateString());
            })
            ->orderBy('id')
            ->value('id');

        return implode(':', [
            'tvio',
            'lesson_booking',
            'teacher_availability',
            $teacher->id,
            $availabilityId ?: 'none',
            $localStart->toDateString(),
        ]);
    }

    private function bookingLockStore(): string
    {
        return (string) config('lessons.booking_locks.store', 'redis');
    }

    private function bookingLockTtlSeconds(): int
    {
        return max(1, (int) config('lessons.booking_locks.ttl_seconds', 60));
    }

    /**
     * Update an existing class schedule.
     *
     * Booking-window changes trigger role and availability validation. The
     * method persists schedule changes, updates the actor stamp, and records
     * auditable changed fields.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    public function update(ClassSchedule $schedule, array $payload, User $actor): ClassSchedule
    {
        $before = clone $schedule;

        [$startsAtUtc, $endsAtUtc] = $this->utcRange(
            $payload['starts_at'] ?? $schedule->starts_at,
            $payload['ends_at'] ?? $schedule->ends_at,
            $payload['timezone'] ?? $schedule->timezone
        );

        $teacher = User::findOrFail($payload['teacher_id'] ?? $schedule->teacher_id);
        $student = User::findOrFail($payload['student_id'] ?? $schedule->student_id);
        $classType = $this->classType($payload, $student, $schedule->class_type);
        $teacherBlockedUntilUtc = $this->teacherBlockedUntil($classType, $startsAtUtc, $endsAtUtc, $payload['timezone'] ?? $schedule->timezone);

        if (! $student->hasRole('student')) {
            throw ValidationException::withMessages([
                'student_id' => 'The selected user must have the student role.',
            ]);
        }

        $changesBookingWindow = array_intersect(array_keys($payload), ['student_id', 'teacher_id', 'class_type', 'timezone', 'starts_at', 'ends_at', 'status']) !== [];

        if ($changesBookingWindow && in_array($payload['status'] ?? $schedule->status, ClassSchedule::BOOKED_STATUSES, true)) {
            $this->availabilityService->assertTeacherCanBeBooked($teacher, $startsAtUtc, $endsAtUtc, $payload['timezone'] ?? $schedule->timezone, $schedule->id, $student, $teacherBlockedUntilUtc);
        }

        $schedule->fill([
            ...Arr::only($payload, ['student_id', 'teacher_id', 'title', 'description', 'status', 'timezone', 'meeting_url', 'notes']),
            'class_type' => $classType,
            'starts_at' => $startsAtUtc,
            'ends_at' => $endsAtUtc,
            'teacher_blocked_until' => $teacherBlockedUntilUtc,
            'updated_by' => $actor->id,
        ]);
        $changedFields = $this->scheduleChangedFields($schedule->getDirty());
        $schedule->save();

        $updatedSchedule = $schedule->refresh();
        $this->logScheduleUpdated($before, $updatedSchedule, $actor, $changedFields);

        return $updatedSchedule;
    }

    /**
     * Reschedule a class by closing the existing schedule and creating a new one.
     *
     * The old schedule is marked rescheduled, the replacement schedule is
     * created and audited, and a reschedule notification is attempted for the
     * participants.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    public function reschedule(ClassSchedule $schedule, array $payload, User $actor): ClassSchedule
    {
        $before = clone $schedule;

        $newSchedule = DB::transaction(function () use ($schedule, $payload, $actor) {
            $schedule->forceFill([
                'status' => ClassSchedule::STATUS_RESCHEDULED,
                'updated_by' => $actor->id,
            ])->save();

            return $this->create([
                'student_id' => $payload['student_id'] ?? $schedule->student_id,
                'teacher_id' => $payload['teacher_id'] ?? $schedule->teacher_id,
                'title' => $payload['title'] ?? $schedule->title,
                'description' => $payload['description'] ?? $schedule->description,
                'class_type' => $payload['class_type'] ?? $schedule->class_type,
                'timezone' => $payload['timezone'] ?? $schedule->timezone,
                'starts_at' => $payload['starts_at'],
                'ends_at' => $payload['ends_at'],
                'meeting_url' => $payload['meeting_url'] ?? $schedule->meeting_url,
                'notes' => $payload['notes'] ?? $schedule->notes,
                'status' => $payload['status'] ?? ClassSchedule::STATUS_PENDING_CONFIRMATION,
                'rescheduled_from_id' => $schedule->id,
            ], $actor);
        })->refresh();

        $this->logScheduleUpdated(
            $before,
            $newSchedule,
            $actor,
            ['status', 'starts_at', 'ends_at', 'timezone', 'rescheduled_from_id']
        );
        $this->notifyRescheduled($schedule->refresh(), $newSchedule, $actor);

        return $newSchedule;
    }

    private function notifyRescheduled(ClassSchedule $oldSchedule, ClassSchedule $newSchedule, User $actor): void
    {
        try {
            $newSchedule->loadMissing(['student:id,name,email,timezone', 'teacher:id,name,email,timezone']);

            $this->notificationService->rescheduleAlert(
                collect([$newSchedule->student, $newSchedule->teacher])->filter()->unique('id')->values()->all(),
                'Class rescheduled: '.($newSchedule->title ?: 'Scheduled class'),
                'Your class was moved to '.$this->scheduleStartLabel($newSchedule).'.',
                [
                    'class_schedule_id' => $newSchedule->id,
                    'rescheduled_from_id' => $oldSchedule->id,
                    'actor_id' => $actor->id,
                ],
                [
                    'email' => true,
                    'source_type' => 'class_schedule',
                    'source_id' => $newSchedule->id,
                    'dedupe_key' => 'class_schedule_rescheduled:'.$newSchedule->id,
                ]
            );
        } catch (Throwable $exception) {
            Log::warning('Reschedule notification delivery failed.', [
                'class_schedule_id' => $newSchedule->id,
                'rescheduled_from_id' => $oldSchedule->id,
                'actor_id' => $actor->id,
                'failure_type' => $exception::class,
            ]);
        }
    }

    private function scheduleStartLabel(ClassSchedule $schedule): string
    {
        return $schedule->starts_at
            ->setTimezone($schedule->timezone)
            ->format('M j, Y g:i A T');
    }

    /**
     * Cancel a class schedule.
     *
     * The schedule status, cancellation actor, timestamp, and reason are
     * persisted, then the status change is recorded in audit logs.
     */
    public function cancel(ClassSchedule $schedule, User $actor, ?string $reason = null): ClassSchedule
    {
        $before = clone $schedule;

        $schedule->forceFill([
            'status' => ClassSchedule::STATUS_CANCELLED,
            'cancelled_by' => $actor->id,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
            'updated_by' => $actor->id,
        ])->save();

        $updatedSchedule = $schedule->refresh();
        $this->logScheduleUpdated($before, $updatedSchedule, $actor, ['status', 'cancelled_by', 'cancelled_at', 'cancellation_reason']);

        return $updatedSchedule;
    }

    /**
     * Update only the status of a class schedule.
     *
     * The method stamps the actor as the updater and records the status change
     * through the schedule audit logging path.
     */
    public function updateStatus(ClassSchedule $schedule, string $status, User $actor): ClassSchedule
    {
        $before = clone $schedule;

        $schedule->forceFill([
            'status' => $status,
            'updated_by' => $actor->id,
        ])->save();

        $updatedSchedule = $schedule->refresh();
        $this->logScheduleUpdated($before, $updatedSchedule, $actor, ['status']);

        return $updatedSchedule;
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function utcRange(mixed $startsAt, mixed $endsAt, string $timezone): array
    {
        return [
            CarbonImmutable::parse($startsAt, $timezone)->utc(),
            CarbonImmutable::parse($endsAt, $timezone)->utc(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function classType(array $payload, User $student, ?string $currentClassType = null): string
    {
        $classType = $payload['class_type']
            ?? $currentClassType
            ?? $student->studentProfile?->class_type
            ?? ClassSchedule::CLASS_TYPE_REGULAR;

        return in_array($classType, ['trial', 'trial_class', 'trial-class'], true)
            ? ClassSchedule::CLASS_TYPE_TRIAL
            : ClassSchedule::CLASS_TYPE_REGULAR;
    }

    private function teacherBlockedUntil(
        string $classType,
        CarbonImmutable $startsAtUtc,
        CarbonImmutable $endsAtUtc,
        string $timezone
    ): CarbonImmutable {
        if ($classType !== ClassSchedule::CLASS_TYPE_TRIAL) {
            return $endsAtUtc;
        }

        $localStart = $startsAtUtc->setTimezone($timezone);
        $localEnd = $endsAtUtc->setTimezone($timezone);

        if ($localStart->minute % 30 !== 0 || $localStart->second !== 0 || ! $localEnd->equalTo($localStart->addMinutes(30))) {
            throw ValidationException::withMessages([
                'ends_at' => 'Trial classes must be booked as one 30-minute interval.',
            ]);
        }

        return $startsAtUtc->addHour();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return Collection<int, array{date: string, starts_at_local: CarbonImmutable, ends_at_local: CarbonImmutable, starts_at_utc: CarbonImmutable, ends_at_utc: CarbonImmutable}>
     */
    private function recurringOccurrences(array $payload): Collection
    {
        $timezone = $payload['timezone'];
        $daysOfWeek = collect($payload['days_of_week'] ?? [$payload['day_of_week']])
            ->map(fn (mixed $day): int => (int) $day)
            ->unique()
            ->sort()
            ->values();

        $startDate = CarbonImmutable::parse($payload['start_date'], $timezone)->startOfDay();
        $endDate = isset($payload['end_date'])
            ? CarbonImmutable::parse($payload['end_date'], $timezone)->startOfDay()
            : null;
        $occurrenceCount = isset($payload['occurrence_count']) ? (int) $payload['occurrence_count'] : null;

        if ($endDate && $endDate->lessThan($startDate)) {
            throw ValidationException::withMessages([
                'end_date' => 'The recurrence end date must be on or after the start date.',
            ]);
        }

        $startTime = CarbonImmutable::parse($payload['start_time'], $timezone)->format('H:i:s');
        $endTime = CarbonImmutable::parse($payload['end_time'], $timezone)->format('H:i:s');
        $occurrences = collect();
        $cursor = $startDate;
        $guardDate = $endDate ?? $startDate->addYears(2);

        while ($cursor->lessThanOrEqualTo($guardDate)) {
            if ($daysOfWeek->contains($cursor->dayOfWeek)) {
                $startsAtLocal = CarbonImmutable::parse($cursor->toDateString().' '.$startTime, $timezone);
                $endsAtLocal = CarbonImmutable::parse($cursor->toDateString().' '.$endTime, $timezone);

                if ($endsAtLocal->lessThanOrEqualTo($startsAtLocal)) {
                    throw ValidationException::withMessages([
                        'end_time' => 'The recurring class end time must be after the start time.',
                    ]);
                }

                $occurrences->push([
                    'date' => $cursor->toDateString(),
                    'starts_at_local' => $startsAtLocal,
                    'ends_at_local' => $endsAtLocal,
                    'starts_at_utc' => $startsAtLocal->utc(),
                    'ends_at_utc' => $endsAtLocal->utc(),
                ]);

                if ($occurrenceCount && $occurrences->count() >= $occurrenceCount) {
                    break;
                }
            }

            $cursor = $cursor->addDay();
        }

        return $occurrences;
    }

    private function validationReason(ValidationException $exception): string
    {
        $errors = $exception->errors();
        $firstField = array_key_first($errors);

        return $firstField ? (string) $errors[$firstField][0] : $exception->getMessage();
    }

    private function logLessonCreated(ClassSchedule $schedule, User $actor): void
    {
        $this->auditLogService->record(
            actorUserId: $actor->id,
            actionType: AuditActionType::LESSON_CREATED,
            module: AuditModule::LESSONS,
            targetEntityType: 'class_schedule',
            targetEntityId: $schedule->id,
            metadata: [
                'lesson_id' => $schedule->id,
                'student_id' => $schedule->student_id,
                'teacher_id' => $schedule->teacher_id,
                'new_status' => $schedule->status,
                'schedule_after' => $this->scheduleSnapshot($schedule),
            ],
        );
    }

    /**
     * @param  array<int, string>  $changedFields
     */
    private function logScheduleUpdated(ClassSchedule $before, ClassSchedule $after, User $actor, array $changedFields): void
    {
        if ($changedFields === []) {
            return;
        }

        $this->auditLogService->record(
            actorUserId: $actor->id,
            actionType: AuditActionType::SCHEDULE_UPDATED,
            module: AuditModule::SCHEDULING,
            targetEntityType: 'class_schedule',
            targetEntityId: $after->id,
            metadata: [
                'lesson_id' => $after->id,
                'student_id' => $after->student_id,
                'teacher_id' => $after->teacher_id,
                'previous_status' => $before->status,
                'new_status' => $after->status,
                'changed_fields' => array_fill_keys(array_values(array_unique($changedFields)), true),
                'schedule_before' => $this->scheduleSnapshot($before),
                'schedule_after' => $this->scheduleSnapshot($after),
                'actor_role' => $this->actorRoleLabel($actor),
                'teacher_or_admin_change' => $actor->hasAnyRole(['teacher', 'admin']),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $dirty
     * @return array<int, string>
     */
    private function scheduleChangedFields(array $dirty): array
    {
        return array_values(array_intersect(array_keys($dirty), [
            'student_id',
            'teacher_id',
            'title',
            'description',
            'status',
            'class_type',
            'timezone',
            'starts_at',
            'ends_at',
            'meeting_url',
            'notes',
            'rescheduled_from_id',
            'cancelled_by',
            'cancelled_at',
            'cancellation_reason',
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function scheduleSnapshot(ClassSchedule $schedule): array
    {
        return [
            'timezone' => $schedule->timezone,
            'starts_at' => $schedule->starts_at?->toIso8601String(),
            'ends_at' => $schedule->ends_at?->toIso8601String(),
        ];
    }

    private function actorRoleLabel(User $actor): string
    {
        if ($actor->hasRole('admin')) {
            return 'admin';
        }

        if ($actor->hasRole('teacher')) {
            return 'teacher';
        }

        if ($actor->hasRole('staff')) {
            return 'staff';
        }

        if ($actor->hasRole('student')) {
            return 'student';
        }

        return 'user';
    }
}
