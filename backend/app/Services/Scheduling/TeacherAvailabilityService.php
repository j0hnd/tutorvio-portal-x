<?php

namespace App\Services\Scheduling;

use App\Models\Scheduling\ClassSchedule;
use App\Models\Scheduling\TeacherAvailability;
use App\Models\Scheduling\TeacherUnavailableDate;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class TeacherAvailabilityService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function createAvailability(array $payload): TeacherAvailability
    {
        $this->assertTeacher($payload['teacher_id']);
        $this->assertTimeRange($payload['start_time'], $payload['end_time']);
        $this->assertNoOverlappingAvailability($payload);

        return TeacherAvailability::create($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateAvailability(TeacherAvailability $availability, array $payload): TeacherAvailability
    {
        $this->assertAvailabilityHasNoBookedSchedules($availability);

        if (array_key_exists('teacher_id', $payload)) {
            $this->assertTeacher($payload['teacher_id']);
        }

        $this->assertTimeRange($payload['start_time'] ?? $availability->start_time, $payload['end_time'] ?? $availability->end_time);
        $this->assertNoOverlappingAvailability([
            'teacher_id' => $payload['teacher_id'] ?? $availability->teacher_id,
            'day_of_week' => $payload['day_of_week'] ?? $availability->day_of_week,
            'start_time' => $payload['start_time'] ?? $availability->start_time,
            'end_time' => $payload['end_time'] ?? $availability->end_time,
            'timezone' => $payload['timezone'] ?? $availability->timezone,
            'effective_from' => $payload['effective_from'] ?? $availability->effective_from,
            'effective_until' => $payload['effective_until'] ?? $availability->effective_until,
            'is_active' => $payload['is_active'] ?? $availability->is_active,
        ], $availability->id);

        $availability->fill($payload)->save();

        return $availability->refresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createUnavailableDate(array $payload): TeacherUnavailableDate
    {
        $this->assertTeacher($payload['teacher_id']);
        $payload = $this->normalizeUnavailableDatePayload($payload);

        [$startsAtUtc, $endsAtUtc] = $this->utcRange($payload['starts_at'], $payload['ends_at'], $payload['timezone']);
        $this->assertNoBookedScheduleConflict($payload['teacher_id'], $startsAtUtc, $endsAtUtc);
        $this->assertNoOverlappingUnavailableDate($payload['teacher_id'], $startsAtUtc, $endsAtUtc);

        return TeacherUnavailableDate::create([
            ...Arr::only($payload, ['teacher_id', 'timezone', 'is_all_day', 'reason']),
            'starts_at' => $startsAtUtc,
            'ends_at' => $endsAtUtc,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateUnavailableDate(TeacherUnavailableDate $unavailableDate, array $payload): TeacherUnavailableDate
    {
        if (array_key_exists('teacher_id', $payload)) {
            $this->assertTeacher($payload['teacher_id']);
        }

        $payload = $this->normalizeUnavailableDatePayload($payload, $unavailableDate);

        [$startsAtUtc, $endsAtUtc] = $this->utcRange(
            $payload['starts_at'] ?? $unavailableDate->starts_at,
            $payload['ends_at'] ?? $unavailableDate->ends_at,
            $payload['timezone'] ?? $unavailableDate->timezone
        );
        $teacherId = $payload['teacher_id'] ?? $unavailableDate->teacher_id;
        $this->assertNoBookedScheduleConflict($teacherId, $startsAtUtc, $endsAtUtc);
        $this->assertNoOverlappingUnavailableDate($teacherId, $startsAtUtc, $endsAtUtc, $unavailableDate->id);

        $unavailableDate->fill([
            ...Arr::only($payload, ['teacher_id', 'timezone', 'is_all_day', 'reason']),
            'starts_at' => $startsAtUtc,
            'ends_at' => $endsAtUtc,
        ])->save();

        return $unavailableDate->refresh();
    }

    public function assertAvailabilityHasNoBookedSchedules(TeacherAvailability $availability): void
    {
        $hasBookedSchedules = ClassSchedule::query()
            ->where('teacher_id', $availability->teacher_id)
            ->whereIn('status', ClassSchedule::BOOKED_STATUSES)
            ->get(['starts_at', 'ends_at', 'teacher_blocked_until'])
            ->contains(function (ClassSchedule $schedule) use ($availability): bool {
                $localStart = $schedule->starts_at->setTimezone($availability->timezone);
                $localEnd = ($schedule->teacher_blocked_until ?? $schedule->ends_at)->setTimezone($availability->timezone);

                if (! $localStart->isSameDay($localEnd) || $localStart->dayOfWeek !== $availability->day_of_week) {
                    return false;
                }

                if ($localStart->format('H:i:s') < $this->timeValue($availability->start_time)
                    || $localEnd->format('H:i:s') > $this->timeValue($availability->end_time)) {
                    return false;
                }

                if ($availability->effective_from && $localStart->toDateString() < $availability->effective_from->toDateString()) {
                    return false;
                }

                return ! ($availability->effective_until && $localStart->toDateString() > $availability->effective_until->toDateString());
            });

        if ($hasBookedSchedules) {
            throw ValidationException::withMessages([
                'availability' => 'Availability slots with booked classes cannot be changed or deleted.',
            ]);
        }
    }

    private function assertTeacher(int $teacherId): void
    {
        if (! User::findOrFail($teacherId)->hasRole('teacher')) {
            throw ValidationException::withMessages([
                'teacher_id' => 'The selected user must have the teacher role.',
            ]);
        }
    }

    private function assertTimeRange(string $startTime, string $endTime): void
    {
        if ($this->timeValue($endTime) <= $this->timeValue($startTime)) {
            throw ValidationException::withMessages([
                'end_time' => 'The availability end time must be after the start time.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertNoOverlappingAvailability(array $payload, ?int $exceptAvailabilityId = null): void
    {
        if (! ($payload['is_active'] ?? true)) {
            return;
        }

        $effectiveFrom = ($payload['effective_from'] ?? null)
            ? CarbonImmutable::parse($payload['effective_from'])->toDateString()
            : null;
        $effectiveUntil = ($payload['effective_until'] ?? null)
            ? CarbonImmutable::parse($payload['effective_until'])->toDateString()
            : null;

        $overlaps = TeacherAvailability::query()
            ->where('teacher_id', $payload['teacher_id'])
            ->where('day_of_week', $payload['day_of_week'])
            ->where('timezone', $payload['timezone'])
            ->where('is_active', true)
            ->whereTime('start_time', '<', $this->timeValue($payload['end_time']))
            ->whereTime('end_time', '>', $this->timeValue($payload['start_time']))
            ->when($exceptAvailabilityId, fn ($query) => $query->whereKeyNot($exceptAvailabilityId))
            ->when($effectiveUntil !== null, function ($query) use ($effectiveUntil) {
                $query->where(function ($query) use ($effectiveUntil) {
                    $query->whereNull('effective_from')
                        ->orWhereDate('effective_from', '<=', $effectiveUntil);
                });
            })
            ->when($effectiveFrom !== null, function ($query) use ($effectiveFrom) {
                $query->where(function ($query) use ($effectiveFrom) {
                    $query->whereNull('effective_until')
                        ->orWhereDate('effective_until', '>=', $effectiveFrom);
                });
            })
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages([
                'start_time' => 'Availability slots for the same teacher cannot overlap.',
            ]);
        }
    }

    private function assertNoOverlappingUnavailableDate(
        int $teacherId,
        CarbonImmutable $startsAtUtc,
        CarbonImmutable $endsAtUtc,
        ?int $exceptUnavailableDateId = null
    ): void {
        $overlaps = TeacherUnavailableDate::query()
            ->where('teacher_id', $teacherId)
            ->where('starts_at', '<', $endsAtUtc)
            ->where('ends_at', '>', $startsAtUtc)
            ->when($exceptUnavailableDateId, fn ($query) => $query->whereKeyNot($exceptUnavailableDateId))
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages([
                'starts_at' => 'Unavailable dates for the same teacher cannot overlap.',
            ]);
        }
    }

    private function assertNoBookedScheduleConflict(int $teacherId, CarbonImmutable $startsAtUtc, CarbonImmutable $endsAtUtc): void
    {
        $conflicts = ClassSchedule::query()
            ->where('teacher_id', $teacherId)
            ->whereIn('status', ClassSchedule::BOOKED_STATUSES)
            ->where('starts_at', '<', $endsAtUtc)
            ->whereRaw('COALESCE(teacher_blocked_until, ends_at) > ?', [$startsAtUtc])
            ->exists();

        if ($conflicts) {
            throw ValidationException::withMessages([
                'starts_at' => 'Unavailable dates cannot overlap booked classes.',
            ]);
        }
    }

    private function timeValue(mixed $time): string
    {
        return CarbonImmutable::parse($time)->format('H:i:s');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeUnavailableDatePayload(array $payload, ?TeacherUnavailableDate $existing = null): array
    {
        $isAllDay = (bool) ($payload['is_all_day'] ?? $existing?->is_all_day ?? false);

        if (! $isAllDay) {
            return $payload;
        }

        $timezone = (string) ($payload['timezone'] ?? $existing?->timezone);
        $startsAt = $payload['starts_at'] ?? $existing?->starts_at;
        $endsAt = $payload['ends_at'] ?? $existing?->ends_at ?? $startsAt;

        $payload['starts_at'] = CarbonImmutable::parse($startsAt, $timezone)->startOfDay()->toDateTimeString();
        $payload['ends_at'] = CarbonImmutable::parse($endsAt, $timezone)->endOfDay()->toDateTimeString();

        return $payload;
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function utcRange(mixed $startsAt, mixed $endsAt, string $timezone): array
    {
        $startsAtUtc = CarbonImmutable::parse($startsAt, $timezone)->utc();
        $endsAtUtc = CarbonImmutable::parse($endsAt, $timezone)->utc();

        if ($endsAtUtc->lessThanOrEqualTo($startsAtUtc)) {
            throw ValidationException::withMessages([
                'ends_at' => 'The end time must be after the start time.',
            ]);
        }

        return [$startsAtUtc, $endsAtUtc];
    }
}
