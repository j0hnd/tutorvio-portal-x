<?php

namespace App\Services\Scheduling;

use App\Models\Scheduling\ClassSchedule;
use App\Models\Scheduling\Holiday;
use App\Models\Scheduling\TeacherAvailability;
use App\Models\Scheduling\TeacherUnavailableDate;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CalendarService
{
    /**
     * @return array<string, mixed>
     */
    public function calendar(User $user, string $view, CarbonImmutable $date, string $timezone): array
    {
        [$startsAt, $endsAt] = $this->rangeForView($view, $date, $timezone);
        $teacherIds = $this->visibleTeacherIds($user);

        return [
            'view' => $view,
            'timezone' => $timezone,
            'range' => [
                'starts_at' => $startsAt->toIso8601String(),
                'ends_at' => $endsAt->toIso8601String(),
                'starts_at_utc' => $startsAt->utc()->toIso8601String(),
                'ends_at_utc' => $endsAt->utc()->toIso8601String(),
            ],
            'availability' => $this->availability($teacherIds, $startsAt, $endsAt, $timezone),
            'booked_lessons' => $this->bookedLessons($user, $startsAt, $endsAt, $timezone),
            'unavailable_dates' => $this->unavailableDates($teacherIds, $startsAt, $endsAt, $timezone),
            'holiday_blocks' => $this->holidayBlocks($startsAt, $endsAt, $timezone),
        ];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function rangeForView(string $view, CarbonImmutable $date, string $timezone): array
    {
        $localDate = $date->setTimezone($timezone);

        return match ($view) {
            'day' => [$localDate->startOfDay(), $localDate->endOfDay()],
            'week' => [$localDate->startOfWeek()->startOfDay(), $localDate->endOfWeek()->endOfDay()],
            'month' => [$localDate->startOfMonth()->startOfDay(), $localDate->endOfMonth()->endOfDay()],
        };
    }

    /**
     * @return array<int>
     */
    private function visibleTeacherIds(User $user): array
    {
        if ($user->hasAnyRole(['admin', 'staff'])) {
            return User::role('teacher')->pluck('id')->all();
        }

        if ($user->hasRole('teacher')) {
            return [$user->id];
        }

        if ($user->hasRole('student')) {
            $assignedTeacherId = $user->studentProfile?->assigned_teacher_id;

            return $assignedTeacherId ? [$assignedTeacherId] : [];
        }

        return [];
    }

    /**
     * @param  array<int>  $teacherIds
     * @return array<int, array<string, mixed>>
     */
    private function availability(array $teacherIds, CarbonImmutable $startsAt, CarbonImmutable $endsAt, string $timezone): array
    {
        if ($teacherIds === []) {
            return [];
        }

        $availabilities = TeacherAvailability::query()
            ->with('teacher:id,public_id,name,email,timezone')
            ->whereIn('teacher_id', $teacherIds)
            ->where('is_active', true)
            ->where(function (Builder $query) use ($endsAt) {
                $query->whereNull('effective_from')
                    ->orWhereDate('effective_from', '<=', $endsAt->addDay()->toDateString());
            })
            ->where(function (Builder $query) use ($startsAt) {
                $query->whereNull('effective_until')
                    ->orWhereDate('effective_until', '>=', $startsAt->subDay()->toDateString());
            })
            ->orderBy('teacher_id')
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        return $availabilities
            ->flatMap(fn (TeacherAvailability $availability) => $this->availabilityOccurrences($availability, $startsAt, $endsAt, $timezone))
            ->sortBy('starts_at')
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function availabilityOccurrences(
        TeacherAvailability $availability,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        string $timezone
    ): Collection {
        $events = collect();
        $cursor = $startsAt->setTimezone($availability->timezone)->subDay()->startOfDay();
        $lastDate = $endsAt->setTimezone($availability->timezone)->addDay()->endOfDay();

        while ($cursor->lessThanOrEqualTo($lastDate)) {
            if ($cursor->dayOfWeek === $availability->day_of_week
                && (! $availability->effective_from || $cursor->toDateString() >= $availability->effective_from->toDateString())
                && (! $availability->effective_until || $cursor->toDateString() <= $availability->effective_until->toDateString())) {
                $start = CarbonImmutable::parse($cursor->toDateString().' '.$this->timeValue($availability->start_time), $availability->timezone)
                    ->setTimezone($timezone);
                $end = CarbonImmutable::parse($cursor->toDateString().' '.$this->timeValue($availability->end_time), $availability->timezone)
                    ->setTimezone($timezone);

                if ($start->lessThanOrEqualTo($endsAt) && $end->greaterThanOrEqualTo($startsAt)) {
                    $events->push([
                        'id' => $this->opaqueId('availability', $availability->id),
                        'type' => 'availability',
                        'teacher' => $this->userPayload($availability->teacher),
                        'starts_at' => $start->toIso8601String(),
                        'ends_at' => $end->toIso8601String(),
                        'timezone' => $availability->timezone,
                        'capacity' => $availability->capacity,
                        'notes' => $availability->notes,
                    ]);
                }
            }

            $cursor = $cursor->addDay();
        }

        return $events;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function bookedLessons(User $user, CarbonImmutable $startsAt, CarbonImmutable $endsAt, string $timezone): array
    {
        return ClassSchedule::query()
            ->with(['student:id,public_id,name,email,timezone', 'teacher:id,public_id,name,email,timezone'])
            ->where('starts_at', '<=', $endsAt->utc())
            ->whereRaw('COALESCE(teacher_blocked_until, ends_at) >= ?', [$startsAt->utc()])
            ->when($user->hasRole('teacher') && ! $user->hasAnyRole(['admin', 'staff']), fn (Builder $query) => $query->where('teacher_id', $user->id))
            ->when($user->hasRole('student') && ! $user->hasAnyRole(['admin', 'staff']), fn (Builder $query) => $query->where('student_id', $user->id))
            ->orderBy('starts_at')
            ->get()
            ->map(fn (ClassSchedule $schedule) => [
                'id' => $schedule->public_id,
                'type' => 'booked_lesson',
                'title' => $schedule->title,
                'description' => $schedule->description,
                'status' => $schedule->status,
                'class_type' => $schedule->class_type,
                'student' => $this->userPayload($schedule->student),
                'teacher' => $this->userPayload($schedule->teacher),
                'starts_at' => $schedule->starts_at->setTimezone($timezone)->toIso8601String(),
                'ends_at' => $schedule->ends_at->setTimezone($timezone)->toIso8601String(),
                'teacher_blocked_until' => ($schedule->teacher_blocked_until ?? $schedule->ends_at)->setTimezone($timezone)->toIso8601String(),
                'timezone' => $schedule->timezone,
                'meeting_url' => $schedule->meeting_url,
                'cancelled_at' => $schedule->cancelled_at?->setTimezone($timezone)->toIso8601String(),
                'cancellation_reason' => $schedule->cancellation_reason,
            ])
            ->all();
    }

    /**
     * @param  array<int>  $teacherIds
     * @return array<int, array<string, mixed>>
     */
    private function unavailableDates(array $teacherIds, CarbonImmutable $startsAt, CarbonImmutable $endsAt, string $timezone): array
    {
        if ($teacherIds === []) {
            return [];
        }

        return TeacherUnavailableDate::query()
            ->with('teacher:id,public_id,name,email,timezone')
            ->whereIn('teacher_id', $teacherIds)
            ->where('starts_at', '<=', $endsAt->utc())
            ->where('ends_at', '>=', $startsAt->utc())
            ->orderBy('starts_at')
            ->get()
            ->map(fn (TeacherUnavailableDate $unavailableDate) => [
                'id' => $this->opaqueId('unavailable_date', $unavailableDate->id),
                'type' => 'unavailable_date',
                'teacher' => $this->userPayload($unavailableDate->teacher),
                'starts_at' => $unavailableDate->starts_at->setTimezone($timezone)->toIso8601String(),
                'ends_at' => $unavailableDate->ends_at->setTimezone($timezone)->toIso8601String(),
                'timezone' => $unavailableDate->timezone,
                'is_all_day' => $unavailableDate->is_all_day,
                'reason' => $unavailableDate->reason,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function holidayBlocks(CarbonImmutable $startsAt, CarbonImmutable $endsAt, string $timezone): array
    {
        return Holiday::query()
            ->where('is_active', true)
            ->where('timezone', $timezone)
            ->where(function (Builder $query) use ($startsAt, $endsAt) {
                $query->whereBetween('date', [$startsAt->toDateString(), $endsAt->toDateString()])
                    ->orWhere('repeats_annually', true);
            })
            ->orderBy('date')
            ->get()
            ->flatMap(function (Holiday $holiday) use ($startsAt, $endsAt, $timezone) {
                $date = CarbonImmutable::parse($holiday->date->toDateString(), $timezone);
                $years = $holiday->repeats_annually
                    ? range($startsAt->year, $endsAt->year)
                    : [$date->year];

                return collect($years)->map(function (int $year) use ($holiday, $date) {
                    $occurrenceDate = $holiday->repeats_annually ? $date->year($year) : $date;

                    return [
                        'id' => $this->opaqueId('holiday', $holiday->id),
                        'type' => 'holiday_block',
                        'name' => $holiday->name,
                        'date' => $occurrenceDate->toDateString(),
                        'starts_at' => $occurrenceDate->startOfDay()->toIso8601String(),
                        'ends_at' => $occurrenceDate->endOfDay()->toIso8601String(),
                        'timezone' => $holiday->timezone,
                        'country_code' => $holiday->country_code,
                        'repeats_annually' => $holiday->repeats_annually,
                        'notes' => $holiday->notes,
                    ];
                });
            })
            ->filter(function (array $holiday) use ($startsAt, $endsAt, $timezone): bool {
                $holidayStartsAt = CarbonImmutable::parse($holiday['starts_at'], $timezone);
                $holidayEndsAt = CarbonImmutable::parse($holiday['ends_at'], $timezone);

                return $holidayStartsAt->lessThanOrEqualTo($endsAt) && $holidayEndsAt->greaterThanOrEqualTo($startsAt);
            })
            ->sortBy('starts_at')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function userPayload(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => $user->public_id,
            'name' => $user->name,
            'email' => $user->email,
            'timezone' => $user->timezone,
        ];
    }

    private function opaqueId(string $type, int|string $id): string
    {
        return $type.'_'.substr(hash_hmac('sha256', (string) $id, (string) config('app.key')), 0, 24);
    }

    private function timeValue(mixed $time): string
    {
        return CarbonImmutable::parse($time)->format('H:i:s');
    }
}
