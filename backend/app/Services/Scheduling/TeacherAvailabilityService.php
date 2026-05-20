<?php

namespace App\Services\Scheduling;

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

        return TeacherAvailability::create($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateAvailability(TeacherAvailability $availability, array $payload): TeacherAvailability
    {
        if (array_key_exists('teacher_id', $payload)) {
            $this->assertTeacher($payload['teacher_id']);
        }

        $this->assertTimeRange($payload['start_time'] ?? $availability->start_time, $payload['end_time'] ?? $availability->end_time);

        $availability->fill($payload)->save();

        return $availability->refresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createUnavailableDate(array $payload): TeacherUnavailableDate
    {
        $this->assertTeacher($payload['teacher_id']);

        [$startsAtUtc, $endsAtUtc] = $this->utcRange($payload['starts_at'], $payload['ends_at'], $payload['timezone']);

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

        [$startsAtUtc, $endsAtUtc] = $this->utcRange(
            $payload['starts_at'] ?? $unavailableDate->starts_at,
            $payload['ends_at'] ?? $unavailableDate->ends_at,
            $payload['timezone'] ?? $unavailableDate->timezone
        );

        $unavailableDate->fill([
            ...Arr::only($payload, ['teacher_id', 'timezone', 'is_all_day', 'reason']),
            'starts_at' => $startsAtUtc,
            'ends_at' => $endsAtUtc,
        ])->save();

        return $unavailableDate->refresh();
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
        if ($endTime <= $startTime) {
            throw ValidationException::withMessages([
                'end_time' => 'The availability end time must be after the start time.',
            ]);
        }
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
