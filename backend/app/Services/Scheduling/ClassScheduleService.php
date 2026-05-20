<?php

namespace App\Services\Scheduling;

use App\Models\Scheduling\ClassSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
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
            $this->availabilityService->assertTeacherCanBeBooked($teacher, $startsAtUtc, $endsAtUtc, $payload['timezone'] ?? $schedule->timezone, $schedule->id);
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
}
