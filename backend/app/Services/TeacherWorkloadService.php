<?php

namespace App\Services;

use App\Models\LessonRecord;
use App\Models\Scheduling\ClassSchedule;
use App\Models\Scheduling\TeacherAvailability;
use App\Models\Scheduling\TeacherUnavailableDate;
use App\Models\TeacherStudentAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TeacherWorkloadService
{
    public const STATUS_AVAILABLE = 'available';

    public const STATUS_NEAR_CAPACITY = 'near_capacity';

    public const STATUS_FULL = 'full';

    public const STATUS_UNAVAILABLE = 'unavailable';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUSES = [
        self::STATUS_AVAILABLE,
        self::STATUS_NEAR_CAPACITY,
        self::STATUS_FULL,
        self::STATUS_UNAVAILABLE,
        self::STATUS_INACTIVE,
    ];

    private const DEFAULT_SLOT_MINUTES = 60;

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function summaries(User $viewer, array $filters): array
    {
        $teachers = User::query()
            ->role('teacher')
            ->with('teacherProfile')
            ->when($viewer->hasRole('teacher') && ! $viewer->hasAnyRole(['admin', 'staff']), fn (Builder $query) => $query->whereKey($viewer->id))
            ->when($filters['teacher_status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderBy('name')
            ->get();

        return $teachers
            ->map(fn (User $teacher) => $this->summary($teacher, $filters))
            ->when($filters['capacity_status'] ?? null, fn (Collection $summaries, string $status) => $summaries->where('workload_status', $status))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function summary(User $teacher, array $filters): array
    {
        [$startsAt, $endsAt, $timezone] = $this->period($filters, $teacher);
        $lessonType = $filters['lesson_type'] ?? null;
        $slotMinutes = (int) ($filters['slot_minutes'] ?? self::DEFAULT_SLOT_MINUTES);

        $activeStudentCount = TeacherStudentAssignment::query()
            ->where('teacher_id', $teacher->id)
            ->active()
            ->count();

        $maximumStudentCapacity = $teacher->teacherProfile?->class_load;
        $availableCapacity = $maximumStudentCapacity === null
            ? null
            : max(0, (int) $maximumStudentCapacity - $activeStudentCount);

        $availabilityWindows = $this->availabilityWindows($teacher, $startsAt, $endsAt);
        $bookedBlocks = $this->bookedBlocks($teacher, $startsAt, $endsAt);
        $unavailableBlocks = $this->unavailableBlocks($teacher, $startsAt, $endsAt);
        $blockingIntervals = collect([...$bookedBlocks, ...$unavailableBlocks]);

        $availableMinutes = $availabilityWindows->sum(fn (array $window): int => $this->minutesBetween($window['starts_at'], $window['ends_at']));
        $bookedMinutes = collect($bookedBlocks)->sum(fn (array $block): int => $this->minutesBetween($block['starts_at'], $block['ends_at']));
        $blockedMinutes = collect($unavailableBlocks)->sum(fn (array $block): int => $this->minutesBetween($block['starts_at'], $block['ends_at']));
        $openMinutes = $this->openIntervals($availabilityWindows, $blockingIntervals)
            ->sum(fn (array $interval): int => $this->minutesBetween($interval['starts_at'], $interval['ends_at']));
        $availableSlotsCount = intdiv(max(0, $openMinutes), max(1, $slotMinutes));

        $lessonRecordCount = $this->lessonRecordCount($teacher, $startsAt, $endsAt, $lessonType);
        $assignedLessonCount = $this->assignedLessonCount($teacher, $startsAt, $endsAt, $lessonType, $lessonRecordCount);

        $scheduleLoadPercent = $availableMinutes > 0
            ? min(100, round(($bookedMinutes / $availableMinutes) * 100, 2))
            : 0.0;

        return [
            'teacher_id' => $teacher->id,
            'teacher' => [
                'id' => $teacher->id,
                'name' => $teacher->name,
                'email' => $teacher->email,
                'status' => $teacher->status,
                'timezone' => $teacher->timezone,
                'internal_status' => $teacher->teacherProfile?->internal_status,
            ],
            'teacher_name' => $teacher->name,
            'period' => [
                'starts_at' => $startsAt->setTimezone($timezone)->toIso8601String(),
                'ends_at' => $endsAt->setTimezone($timezone)->toIso8601String(),
                'timezone' => $timezone,
            ],
            'active_student_count' => $activeStudentCount,
            'maximum_student_capacity' => $maximumStudentCapacity,
            'available_capacity' => $availableCapacity,
            'current_schedule_load' => [
                'available_minutes' => $availableMinutes,
                'booked_minutes' => $bookedMinutes,
                'blocked_minutes' => $blockedMinutes,
                'open_minutes' => $openMinutes,
                'utilization_percent' => $scheduleLoadPercent,
            ],
            'assigned_lesson_count' => $assignedLessonCount,
            'lesson_record_count' => $lessonRecordCount,
            'available_slots_count' => $availableSlotsCount,
            'slot_minutes' => $slotMinutes,
            'unavailable_periods' => collect($unavailableBlocks)
                ->map(fn (array $block): array => [
                    'id' => $block['id'],
                    'starts_at' => $block['starts_at']->setTimezone($timezone)->toIso8601String(),
                    'ends_at' => $block['ends_at']->setTimezone($timezone)->toIso8601String(),
                    'timezone' => $block['timezone'],
                    'is_all_day' => $block['is_all_day'],
                    'reason' => $block['reason'],
                ])
                ->values()
                ->all(),
            'workload_status' => $this->workloadStatus(
                $teacher,
                $activeStudentCount,
                $maximumStudentCapacity,
                $availableCapacity,
                $availableMinutes,
                $availableSlotsCount,
                $scheduleLoadPercent
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string}
     */
    private function period(array $filters, User $teacher): array
    {
        $timezone = (string) ($filters['timezone'] ?? $teacher->timezone ?? config('app.timezone', 'UTC'));
        $startsAt = isset($filters['from'])
            ? CarbonImmutable::parse($filters['from'], $timezone)->startOfDay()
            : CarbonImmutable::now($timezone)->startOfDay();
        $endsAt = isset($filters['to'])
            ? CarbonImmutable::parse($filters['to'], $timezone)->endOfDay()
            : $startsAt->addDays(6)->endOfDay();

        return [$startsAt->utc(), $endsAt->utc(), $timezone];
    }

    /**
     * @return Collection<int, array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}>
     */
    private function availabilityWindows(User $teacher, CarbonImmutable $startsAt, CarbonImmutable $endsAt): Collection
    {
        return TeacherAvailability::query()
            ->where('teacher_id', $teacher->id)
            ->where('is_active', true)
            ->where(function (Builder $query) use ($endsAt) {
                $query->whereNull('effective_from')
                    ->orWhereDate('effective_from', '<=', $endsAt->toDateString());
            })
            ->where(function (Builder $query) use ($startsAt) {
                $query->whereNull('effective_until')
                    ->orWhereDate('effective_until', '>=', $startsAt->toDateString());
            })
            ->get()
            ->flatMap(fn (TeacherAvailability $availability) => $this->availabilityOccurrences($availability, $startsAt, $endsAt))
            ->sortBy(fn (array $window): int => $window['starts_at']->getTimestamp())
            ->values();
    }

    /**
     * @return Collection<int, array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}>
     */
    private function availabilityOccurrences(TeacherAvailability $availability, CarbonImmutable $startsAt, CarbonImmutable $endsAt): Collection
    {
        $events = collect();
        $cursor = $startsAt->setTimezone($availability->timezone)->startOfDay();
        $lastDate = $endsAt->setTimezone($availability->timezone)->endOfDay();

        while ($cursor->lessThanOrEqualTo($lastDate)) {
            if ($cursor->dayOfWeek === $availability->day_of_week
                && (! $availability->effective_from || $cursor->toDateString() >= $availability->effective_from->toDateString())
                && (! $availability->effective_until || $cursor->toDateString() <= $availability->effective_until->toDateString())) {
                $start = CarbonImmutable::parse($cursor->toDateString().' '.$this->timeValue($availability->start_time), $availability->timezone)->utc();
                $end = CarbonImmutable::parse($cursor->toDateString().' '.$this->timeValue($availability->end_time), $availability->timezone)->utc();

                if ($start->lessThan($endsAt) && $end->greaterThan($startsAt)) {
                    $events->push([
                        'starts_at' => $start->max($startsAt),
                        'ends_at' => $end->min($endsAt),
                    ]);
                }
            }

            $cursor = $cursor->addDay();
        }

        return $events;
    }

    /**
     * @return array<int, array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}>
     */
    private function bookedBlocks(User $teacher, CarbonImmutable $startsAt, CarbonImmutable $endsAt): array
    {
        return ClassSchedule::query()
            ->where('teacher_id', $teacher->id)
            ->whereIn('status', ClassSchedule::BOOKED_STATUSES)
            ->where('starts_at', '<', $endsAt)
            ->whereRaw('COALESCE(teacher_blocked_until, ends_at) > ?', [$startsAt])
            ->get(['starts_at', 'ends_at', 'teacher_blocked_until'])
            ->map(fn (ClassSchedule $schedule): array => [
                'starts_at' => $schedule->starts_at->utc()->max($startsAt),
                'ends_at' => ($schedule->teacher_blocked_until ?? $schedule->ends_at)->utc()->min($endsAt),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function unavailableBlocks(User $teacher, CarbonImmutable $startsAt, CarbonImmutable $endsAt): array
    {
        return TeacherUnavailableDate::query()
            ->where('teacher_id', $teacher->id)
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->orderBy('starts_at')
            ->get()
            ->map(fn (TeacherUnavailableDate $block): array => [
                'id' => $block->id,
                'starts_at' => $block->starts_at->utc()->max($startsAt),
                'ends_at' => $block->ends_at->utc()->min($endsAt),
                'timezone' => $block->timezone,
                'is_all_day' => $block->is_all_day,
                'reason' => $block->reason,
            ])
            ->all();
    }

    /**
     * @param  Collection<int, array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}>  $availabilityWindows
     * @param  Collection<int, array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}>  $blockingIntervals
     * @return Collection<int, array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}>
     */
    private function openIntervals(Collection $availabilityWindows, Collection $blockingIntervals): Collection
    {
        return $availabilityWindows->flatMap(function (array $window) use ($blockingIntervals): array {
            $open = [$window];

            foreach ($blockingIntervals as $block) {
                $next = [];

                foreach ($open as $interval) {
                    if ($block['ends_at']->lessThanOrEqualTo($interval['starts_at']) || $block['starts_at']->greaterThanOrEqualTo($interval['ends_at'])) {
                        $next[] = $interval;

                        continue;
                    }

                    if ($block['starts_at']->greaterThan($interval['starts_at'])) {
                        $next[] = [
                            'starts_at' => $interval['starts_at'],
                            'ends_at' => $block['starts_at']->min($interval['ends_at']),
                        ];
                    }

                    if ($block['ends_at']->lessThan($interval['ends_at'])) {
                        $next[] = [
                            'starts_at' => $block['ends_at']->max($interval['starts_at']),
                            'ends_at' => $interval['ends_at'],
                        ];
                    }
                }

                $open = $next;
            }

            return $open;
        })->values();
    }

    private function assignedLessonCount(
        User $teacher,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        ?string $lessonType,
        int $lessonRecordCount
    ): int {
        if ($lessonType && in_array($lessonType, LessonRecord::LESSON_TYPES, true) && ! in_array($lessonType, ClassSchedule::CLASS_TYPES, true)) {
            return $lessonRecordCount;
        }

        return ClassSchedule::query()
            ->where('teacher_id', $teacher->id)
            ->whereIn('status', ClassSchedule::BOOKED_STATUSES)
            ->where('starts_at', '<', $endsAt)
            ->whereRaw('COALESCE(teacher_blocked_until, ends_at) > ?', [$startsAt])
            ->when($lessonType && in_array($lessonType, ClassSchedule::CLASS_TYPES, true), fn (Builder $query) => $query->where('class_type', $lessonType))
            ->count();
    }

    private function lessonRecordCount(User $teacher, CarbonImmutable $startsAt, CarbonImmutable $endsAt, ?string $lessonType): int
    {
        return LessonRecord::query()
            ->where('teacher_id', $teacher->id)
            ->whereBetween('scheduled_date', [$startsAt->toDateString(), $endsAt->toDateString()])
            ->when($lessonType && in_array($lessonType, LessonRecord::LESSON_TYPES, true), fn (Builder $query) => $query->where('lesson_type', $lessonType))
            ->count();
    }

    private function workloadStatus(
        User $teacher,
        int $activeStudentCount,
        ?int $maximumStudentCapacity,
        ?int $availableCapacity,
        int $availableMinutes,
        int $availableSlotsCount,
        float $scheduleLoadPercent
    ): string {
        if ($teacher->status !== User::STATUS_ACTIVE) {
            return self::STATUS_INACTIVE;
        }

        if ($teacher->teacherProfile?->internal_status !== null
            && ! in_array($teacher->teacherProfile->internal_status, ['available', 'active'], true)) {
            return self::STATUS_UNAVAILABLE;
        }

        if ($availableMinutes === 0) {
            return self::STATUS_UNAVAILABLE;
        }

        if ($maximumStudentCapacity !== null && $activeStudentCount >= $maximumStudentCapacity) {
            return self::STATUS_FULL;
        }

        if ($availableSlotsCount === 0 || $scheduleLoadPercent >= 100) {
            return $scheduleLoadPercent > 0 ? self::STATUS_FULL : self::STATUS_UNAVAILABLE;
        }

        if (($maximumStudentCapacity !== null && $maximumStudentCapacity > 0 && ($activeStudentCount / $maximumStudentCapacity) >= 0.8)
            || ($availableCapacity !== null && $availableCapacity <= 1)
            || $scheduleLoadPercent >= 80) {
            return self::STATUS_NEAR_CAPACITY;
        }

        return self::STATUS_AVAILABLE;
    }

    private function minutesBetween(CarbonInterface $startsAt, CarbonInterface $endsAt): int
    {
        return max(0, (int) $startsAt->diffInMinutes($endsAt, true));
    }

    private function timeValue(mixed $time): string
    {
        return CarbonImmutable::parse($time)->format('H:i:s');
    }
}
