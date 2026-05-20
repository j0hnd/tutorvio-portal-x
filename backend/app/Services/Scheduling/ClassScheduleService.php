<?php

namespace App\Services\Scheduling;

use App\Models\Scheduling\ClassSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClassScheduleService
{
    public function __construct(private readonly SchedulingAvailabilityService $availabilityService) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload, User $actor): ClassSchedule
    {
        [$startsAtUtc, $endsAtUtc] = $this->utcRange($payload['starts_at'], $payload['ends_at'], $payload['timezone']);
        $teacher = User::findOrFail($payload['teacher_id']);
        $student = User::findOrFail($payload['student_id']);

        if (! $student->hasRole('student')) {
            throw ValidationException::withMessages([
                'student_id' => 'The selected user must have the student role.',
            ]);
        }

        $this->availabilityService->assertTeacherCanBeBooked($teacher, $startsAtUtc, $endsAtUtc, $payload['timezone']);

        return ClassSchedule::create([
            ...Arr::only($payload, ['student_id', 'teacher_id', 'title', 'description', 'timezone', 'meeting_url', 'notes', 'rescheduled_from_id']),
            'status' => $payload['status'] ?? ClassSchedule::STATUS_SCHEDULED,
            'starts_at' => $startsAtUtc,
            'ends_at' => $endsAtUtc,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{created: array<int, ClassSchedule>, skipped: array<int, array<string, string>>, requested_occurrences: int}
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
        $created = [];
        $skipped = [];

        DB::transaction(function () use ($payload, $actor, $teacher, $student, $occurrences, &$created, &$skipped): void {
            foreach ($occurrences as $occurrence) {
                try {
                    $this->availabilityService->assertTeacherCanBeBooked(
                        $teacher,
                        $occurrence['starts_at_utc'],
                        $occurrence['ends_at_utc'],
                        $payload['timezone'],
                        student: $student
                    );

                    $created[] = ClassSchedule::create([
                        ...Arr::only($payload, ['student_id', 'teacher_id', 'title', 'description', 'timezone', 'meeting_url', 'notes']),
                        'status' => $payload['status'] ?? ClassSchedule::STATUS_SCHEDULED,
                        'starts_at' => $occurrence['starts_at_utc'],
                        'ends_at' => $occurrence['ends_at_utc'],
                        'created_by' => $actor->id,
                        'updated_by' => $actor->id,
                    ]);
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
     * @param  array<string, mixed>  $payload
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

        return DB::transaction(function () use ($payload, $student, $teacher, $startsAtUtc, $endsAtUtc): ClassSchedule {
            $this->availabilityService->assertTeacherCanBeBooked(
                $teacher,
                $startsAtUtc,
                $endsAtUtc,
                $payload['timezone'],
                student: $student
            );

            return ClassSchedule::create([
                ...Arr::only($payload, ['teacher_id', 'title', 'description', 'timezone', 'meeting_url', 'notes']),
                'student_id' => $student->id,
                'status' => $payload['status'] ?? ClassSchedule::STATUS_PENDING_CONFIRMATION,
                'starts_at' => $startsAtUtc,
                'ends_at' => $endsAtUtc,
                'created_by' => $student->id,
                'updated_by' => $student->id,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(ClassSchedule $schedule, array $payload, User $actor): ClassSchedule
    {
        [$startsAtUtc, $endsAtUtc] = $this->utcRange(
            $payload['starts_at'] ?? $schedule->starts_at,
            $payload['ends_at'] ?? $schedule->ends_at,
            $payload['timezone'] ?? $schedule->timezone
        );

        $teacher = User::findOrFail($payload['teacher_id'] ?? $schedule->teacher_id);
        $student = User::findOrFail($payload['student_id'] ?? $schedule->student_id);

        if (! $student->hasRole('student')) {
            throw ValidationException::withMessages([
                'student_id' => 'The selected user must have the student role.',
            ]);
        }

        $changesBookingWindow = array_intersect(array_keys($payload), ['student_id', 'teacher_id', 'timezone', 'starts_at', 'ends_at', 'status']) !== [];

        if ($changesBookingWindow && in_array($payload['status'] ?? $schedule->status, ClassSchedule::BOOKED_STATUSES, true)) {
            $this->availabilityService->assertTeacherCanBeBooked($teacher, $startsAtUtc, $endsAtUtc, $payload['timezone'] ?? $schedule->timezone, $schedule->id, $student);
        }

        $schedule->fill([
            ...Arr::only($payload, ['student_id', 'teacher_id', 'title', 'description', 'status', 'timezone', 'meeting_url', 'notes']),
            'starts_at' => $startsAtUtc,
            'ends_at' => $endsAtUtc,
            'updated_by' => $actor->id,
        ]);
        $schedule->save();

        return $schedule->refresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function reschedule(ClassSchedule $schedule, array $payload, User $actor): ClassSchedule
    {
        return DB::transaction(function () use ($schedule, $payload, $actor) {
            $schedule->forceFill([
                'status' => ClassSchedule::STATUS_RESCHEDULED,
                'updated_by' => $actor->id,
            ])->save();

            return $this->create([
                'student_id' => $payload['student_id'] ?? $schedule->student_id,
                'teacher_id' => $payload['teacher_id'] ?? $schedule->teacher_id,
                'title' => $payload['title'] ?? $schedule->title,
                'description' => $payload['description'] ?? $schedule->description,
                'timezone' => $payload['timezone'] ?? $schedule->timezone,
                'starts_at' => $payload['starts_at'],
                'ends_at' => $payload['ends_at'],
                'meeting_url' => $payload['meeting_url'] ?? $schedule->meeting_url,
                'notes' => $payload['notes'] ?? $schedule->notes,
                'status' => $payload['status'] ?? ClassSchedule::STATUS_PENDING_CONFIRMATION,
                'rescheduled_from_id' => $schedule->id,
            ], $actor);
        })->refresh();
    }

    public function cancel(ClassSchedule $schedule, User $actor, ?string $reason = null): ClassSchedule
    {
        $schedule->forceFill([
            'status' => ClassSchedule::STATUS_CANCELLED,
            'cancelled_by' => $actor->id,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
            'updated_by' => $actor->id,
        ])->save();

        return $schedule->refresh();
    }

    public function updateStatus(ClassSchedule $schedule, string $status, User $actor): ClassSchedule
    {
        $schedule->forceFill([
            'status' => $status,
            'updated_by' => $actor->id,
        ])->save();

        return $schedule->refresh();
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
}
