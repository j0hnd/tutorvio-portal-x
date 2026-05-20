<?php

namespace App\Services\Scheduling;

use App\Models\Scheduling\ClassSchedule;
use App\Models\Scheduling\Holiday;
use App\Models\Scheduling\TeacherAvailability;
use App\Models\Scheduling\TeacherUnavailableDate;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class SchedulingAvailabilityService
{
    public function assertTeacherCanBeBooked(
        User $teacher,
        CarbonImmutable $startsAtUtc,
        CarbonImmutable $endsAtUtc,
        string $timezone,
        ?int $exceptScheduleId = null,
        ?User $student = null
    ): void {
        if (! $teacher->hasRole('teacher')) {
            throw ValidationException::withMessages([
                'teacher_id' => 'The selected user must have the teacher role.',
            ]);
        }

        if ($endsAtUtc->lessThanOrEqualTo($startsAtUtc)) {
            throw ValidationException::withMessages([
                'ends_at' => 'The class end time must be after the start time.',
            ]);
        }

        $this->assertInsideAvailabilityWindow($teacher, $startsAtUtc, $endsAtUtc, $timezone);
        $this->assertNoUnavailableDate($teacher, $startsAtUtc, $endsAtUtc);
        $this->assertNoHoliday($startsAtUtc, $endsAtUtc, $timezone);
        $this->assertNoBookedScheduleConflict($teacher, $startsAtUtc, $endsAtUtc, $exceptScheduleId);

        if ($student) {
            $this->assertNoStudentScheduleConflict($student, $startsAtUtc, $endsAtUtc, $exceptScheduleId);
        }
    }

    private function assertInsideAvailabilityWindow(
        User $teacher,
        CarbonImmutable $startsAtUtc,
        CarbonImmutable $endsAtUtc,
        string $timezone
    ): void {
        $localStart = $startsAtUtc->setTimezone($timezone);
        $localEnd = $endsAtUtc->setTimezone($timezone);

        if (! $localStart->isSameDay($localEnd)) {
            throw ValidationException::withMessages([
                'ends_at' => 'Classes must fit inside one local availability day.',
            ]);
        }

        $available = TeacherAvailability::query()
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
            ->exists();

        if (! $available) {
            throw ValidationException::withMessages([
                'starts_at' => 'The class is outside the teacher availability window.',
            ]);
        }
    }

    private function assertNoUnavailableDate(User $teacher, CarbonImmutable $startsAtUtc, CarbonImmutable $endsAtUtc): void
    {
        $blocked = TeacherUnavailableDate::query()
            ->where('teacher_id', $teacher->id)
            ->where('starts_at', '<', $endsAtUtc)
            ->where('ends_at', '>', $startsAtUtc)
            ->exists();

        if ($blocked) {
            throw ValidationException::withMessages([
                'starts_at' => 'The teacher is unavailable during this time.',
            ]);
        }
    }

    private function assertNoHoliday(CarbonImmutable $startsAtUtc, CarbonImmutable $endsAtUtc, string $timezone): void
    {
        $localStart = $startsAtUtc->setTimezone($timezone);
        $localEnd = $endsAtUtc->setTimezone($timezone);

        $blocked = Holiday::query()
            ->where('is_active', true)
            ->where('timezone', $timezone)
            ->whereDate('date', '>=', $localStart->toDateString())
            ->whereDate('date', '<=', $localEnd->toDateString())
            ->exists();

        if ($blocked) {
            throw ValidationException::withMessages([
                'starts_at' => 'The class falls on a configured holiday.',
            ]);
        }
    }

    private function assertNoBookedScheduleConflict(
        User $teacher,
        CarbonImmutable $startsAtUtc,
        CarbonImmutable $endsAtUtc,
        ?int $exceptScheduleId
    ): void {
        $conflicts = ClassSchedule::query()
            ->where('teacher_id', $teacher->id)
            ->whereIn('status', ClassSchedule::BOOKED_STATUSES)
            ->where('starts_at', '<', $endsAtUtc)
            ->where('ends_at', '>', $startsAtUtc)
            ->when($exceptScheduleId, fn ($query) => $query->whereKeyNot($exceptScheduleId))
            ->exists();

        if ($conflicts) {
            throw ValidationException::withMessages([
                'starts_at' => 'The teacher already has a class scheduled during this time.',
            ]);
        }
    }

    private function assertNoStudentScheduleConflict(
        User $student,
        CarbonImmutable $startsAtUtc,
        CarbonImmutable $endsAtUtc,
        ?int $exceptScheduleId
    ): void {
        $conflicts = ClassSchedule::query()
            ->where('student_id', $student->id)
            ->whereIn('status', ClassSchedule::BOOKED_STATUSES)
            ->where('starts_at', '<', $endsAtUtc)
            ->where('ends_at', '>', $startsAtUtc)
            ->when($exceptScheduleId, fn ($query) => $query->whereKeyNot($exceptScheduleId))
            ->exists();

        if ($conflicts) {
            throw ValidationException::withMessages([
                'starts_at' => 'The student already has a class scheduled during this time.',
            ]);
        }
    }
}
