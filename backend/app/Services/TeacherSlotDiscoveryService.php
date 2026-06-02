<?php

namespace App\Services;

use App\Models\TeacherStudentAssignment;
use App\Models\User;
use Illuminate\Support\Str;

class TeacherSlotDiscoveryService
{
    public function __construct(private readonly TeacherWorkloadService $workloads) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function discoverForStudent(User $student, array $filters = []): array
    {
        $student->loadMissing('studentProfile');

        $filters = $this->normalizeFilters($student, $filters);

        $teachers = User::query()
            ->role('teacher')
            ->with('teacherProfile')
            ->where('status', User::STATUS_ACTIVE)
            ->orderBy('name')
            ->get();

        return $teachers
            ->map(fn (User $teacher): array => $this->evaluateTeacherForStudent($student, $teacher, $filters, true))
            ->filter(fn (array $option): bool => $option['is_available'])
            ->sortBy([
                ['rank_score', 'desc'],
                ['teacher.name', 'asc'],
            ])
            ->values()
            ->when(isset($filters['max_results']), fn ($options) => $options->take((int) $filters['max_results']))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function evaluateTeacherForStudent(
        User $student,
        User $teacher,
        array $filters = [],
        bool $requireOpenSchedule = false
    ): array {
        $student->loadMissing('studentProfile');
        $teacher->loadMissing('teacherProfile');

        $filters = $this->normalizeFilters($student, $filters);
        $summary = $this->workloads->summary($teacher, $filters);
        $activeStudentCountForCapacity = TeacherStudentAssignment::query()
            ->where('teacher_id', $teacher->id)
            ->active()
            ->where('student_id', '!=', $student->id)
            ->count();

        $maxCapacity = $teacher->teacherProfile?->class_load;
        $hasCapacity = $maxCapacity === null || $activeStudentCountForCapacity < (int) $maxCapacity;
        $hasOpenSchedule = (int) $summary['available_slots_count'] > 0;
        $compatibleLessonType = $this->isCompatible($teacher, $filters);
        $teacherAvailable = $teacher->status === User::STATUS_ACTIVE
            && ($teacher->teacherProfile?->internal_status === null
                || in_array($teacher->teacherProfile->internal_status, ['available', 'active'], true));

        $isAvailable = $teacherAvailable
            && $hasCapacity
            && $compatibleLessonType
            && (! $requireOpenSchedule || $hasOpenSchedule);

        $availableCapacity = $maxCapacity === null
            ? null
            : max(0, (int) $maxCapacity - $activeStudentCountForCapacity);

        return [
            'teacher_id' => $teacher->id,
            'teacher' => [
                'id' => $teacher->id,
                'name' => $teacher->name,
                'email' => $teacher->email,
                'timezone' => $teacher->timezone,
                'status' => $teacher->status,
                'internal_status' => $teacher->teacherProfile?->internal_status,
            ],
            'is_available' => $isAvailable,
            'rank_score' => $this->rankScore($summary, $availableCapacity, $compatibleLessonType),
            'reasons' => [
                'has_capacity' => $hasCapacity,
                'has_open_schedule' => $hasOpenSchedule,
                'compatible_lesson_type' => $compatibleLessonType,
                'available_slots' => (int) $summary['available_slots_count'],
                'current_active_students' => (int) $summary['active_student_count'],
                'max_capacity' => $maxCapacity,
            ],
            'available_slots' => (int) $summary['available_slots_count'],
            'current_active_students' => (int) $summary['active_student_count'],
            'max_capacity' => $maxCapacity,
            'available_capacity' => $availableCapacity,
            'schedule_load' => $summary['current_schedule_load'],
            'workload_status' => $summary['workload_status'],
            'period' => $summary['period'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     */
    public function assignmentErrors(User $student, User $teacher, array $filters = []): array
    {
        $evaluation = $this->evaluateTeacherForStudent($student, $teacher, $filters);
        $errors = [];

        if ($student->status !== User::STATUS_ACTIVE || ! $student->hasRole('student')) {
            $errors['student_id'] = 'The selected student must be an active student.';
        }

        if ($teacher->status !== User::STATUS_ACTIVE || ! $teacher->hasRole('teacher')) {
            $errors['teacher_id'] = 'The selected teacher must be an active teacher.';
        } elseif (! $evaluation['is_available']) {
            if (! $evaluation['reasons']['has_capacity']) {
                $errors['teacher_id'] = 'The selected teacher has reached assignment capacity.';
            } elseif (! $evaluation['reasons']['compatible_lesson_type']) {
                $errors['teacher_id'] = 'The selected teacher is not compatible with the selected lesson type.';
            } else {
                $errors['teacher_id'] = 'The selected teacher is currently unavailable.';
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function normalizeFilters(User $student, array $filters): array
    {
        $student->loadMissing('studentProfile');

        $filters['timezone'] ??= $student->timezone ?: config('app.timezone', 'UTC');
        $filters['lesson_type'] ??= $student->studentProfile?->class_type;
        $filters['course'] ??= $student->studentProfile?->course;

        return array_filter($filters, fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function isCompatible(User $teacher, array $filters): bool
    {
        $targets = collect([$filters['lesson_type'] ?? null, $filters['course'] ?? null])
            ->filter(fn (?string $value): bool => $value !== null && $value !== '')
            ->map(fn (string $value): string => Str::of($value)->lower()->squish()->toString())
            ->values();

        if ($targets->isEmpty()) {
            return true;
        }

        $profileText = collect([
            $teacher->teacherProfile?->specialization,
            $teacher->teacherProfile?->expertise,
        ])
            ->filter()
            ->map(fn (string $value): string => Str::of($value)->lower()->squish()->toString())
            ->implode(' ');

        if ($profileText === '') {
            return true;
        }

        return $targets->contains(fn (string $target): bool => str_contains($profileText, $target));
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function rankScore(array $summary, ?int $availableCapacity, bool $compatibleLessonType): float
    {
        $openSlots = (int) $summary['available_slots_count'];
        $capacityScore = $availableCapacity === null ? 5 : min(5, $availableCapacity);
        $utilization = (float) ($summary['current_schedule_load']['utilization_percent'] ?? 0);

        return ($openSlots * 10)
            + ($capacityScore * 5)
            + ($compatibleLessonType ? 20 : 0)
            - $utilization;
    }
}
