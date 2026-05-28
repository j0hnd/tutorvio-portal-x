<?php

namespace App\Reports;

use App\Models\CourseProgramStudentAssignment;
use App\Models\Scheduling\ClassSchedule;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TrialEnrollmentReport
{
    public const ENROLLMENT_STATUS_ENROLLED = 'enrolled';

    public const ENROLLMENT_STATUS_NOT_CONVERTED = 'not_converted';

    public const ENROLLMENT_STATUSES = [
        self::ENROLLMENT_STATUS_ENROLLED,
        self::ENROLLMENT_STATUS_NOT_CONVERTED,
    ];

    /**
     * @return array{
     *     summary: array<string, int|float|null>,
     *     rows: list<array<string, mixed>>,
     *     filters: array<string, mixed>
     * }
     */
    public function generate(SchoolReportFilters $filters): array
    {
        $trialClasses = $this->baseQuery($filters)->get();
        $rows = $trialClasses
            ->map(fn (ClassSchedule $trialClass) => $this->row($trialClass))
            ->when(
                in_array($filters->status(), self::ENROLLMENT_STATUSES, true),
                fn (Collection $rows) => $rows->where('enrollment_status', $filters->status())
            )
            ->values();

        return [
            'summary' => $this->summary($rows),
            'rows' => $rows->all(),
            'filters' => $filters->toArray(),
        ];
    }

    /**
     * @return Builder<ClassSchedule>
     */
    private function baseQuery(SchoolReportFilters $filters): Builder
    {
        return ClassSchedule::query()
            ->where('class_type', ClassSchedule::CLASS_TYPE_TRIAL)
            ->with([
                'teacher:id,name,email,status',
                'student:id,name,email,status',
                'student.studentProfile.assignedTeacher:id,name,email,status',
                'student.courseProgramAssignments' => fn ($query) => $query
                    ->active()
                    ->with('courseProgram:id,title,placement_level')
                    ->latest('start_date')
                    ->latest('assigned_at')
                    ->latest('id'),
                'student.subscriptions' => fn ($query) => $query
                    ->orderBy('starts_at')
                    ->orderBy('id'),
            ])
            ->when($filters->dateFrom(), fn (Builder $query, string $date) => $query->whereDate('starts_at', '>=', $date))
            ->when($filters->dateTo(), fn (Builder $query, string $date) => $query->whereDate('starts_at', '<=', $date))
            ->when($filters->teacherId(), fn (Builder $query, int $teacherId) => $query->where('teacher_id', $teacherId))
            ->when($filters->studentId(), fn (Builder $query, int $studentId) => $query->where('student_id', $studentId))
            ->when(
                $filters->courseId(),
                fn (Builder $query, int $courseId) => $query->whereHas(
                    'student.courseProgramAssignments',
                    fn (Builder $query) => $query->active()->where('course_program_id', $courseId)
                )
            )
            ->when(
                $filters->status() && ! in_array($filters->status(), self::ENROLLMENT_STATUSES, true),
                fn (Builder $query) => $query->where('status', $filters->status())
            )
            ->orderByDesc('starts_at')
            ->orderByDesc('id');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, int|float|null>
     */
    private function summary(Collection $rows): array
    {
        $studentIds = $rows->pluck('student_id')->unique();
        $convertedStudentIds = $rows
            ->where('enrollment_status', self::ENROLLMENT_STATUS_ENROLLED)
            ->pluck('student_id')
            ->unique();
        $totalTrialStudents = $studentIds->count();
        $convertedCount = $convertedStudentIds->count();

        return [
            'total_trial_students_count' => $totalTrialStudents,
            'total_trial_classes_count' => $rows->count(),
            'converted_enrolled_count' => $convertedCount,
            'not_converted_count' => $totalTrialStudents - $convertedCount,
            // No persisted follow-up state exists for trial classes/prospects yet.
            'pending_follow_up_count' => null,
            'trial_to_enrollment_conversion_rate' => $totalTrialStudents > 0
                ? round(($convertedCount / $totalTrialStudents) * 100, 2)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(ClassSchedule $trialClass): array
    {
        $enrollment = $this->enrollmentFor($trialClass);
        $courseAssignment = $this->courseAssignmentFor($trialClass);
        $student = $trialClass->student;
        $teacher = $trialClass->teacher;

        return [
            'student_id' => $student?->id,
            'prospect_id' => null,
            'student_name' => $student?->name,
            'prospect_name' => null,
            'trial_lesson_date' => $trialClass->starts_at?->toDateString(),
            'trial_teacher' => $teacher ? [
                'id' => $teacher->id,
                'name' => $teacher->name,
            ] : null,
            'course' => $this->course($trialClass, $courseAssignment),
            'trial_status' => $trialClass->status,
            'enrollment_status' => $enrollment ? self::ENROLLMENT_STATUS_ENROLLED : self::ENROLLMENT_STATUS_NOT_CONVERTED,
            'enrollment_date' => $enrollment?->starts_at?->toDateString(),
            // No follow-up table/status column exists for trial conversion tracking yet.
            'follow_up_status' => null,
        ];
    }

    private function enrollmentFor(ClassSchedule $trialClass): ?Subscription
    {
        $trialDate = $trialClass->starts_at?->startOfDay();

        if ($trialDate === null || $trialClass->student === null) {
            return null;
        }

        return $trialClass->student->subscriptions
            ->first(fn (Subscription $subscription) => $subscription->starts_at !== null
                && $subscription->starts_at->greaterThanOrEqualTo($trialDate));
    }

    private function courseAssignmentFor(ClassSchedule $trialClass): ?CourseProgramStudentAssignment
    {
        return $trialClass->student?->courseProgramAssignments->first();
    }

    /**
     * @return array{id: int|null, title: string|null, placement_level: string|null}|null
     */
    private function course(ClassSchedule $trialClass, ?CourseProgramStudentAssignment $courseAssignment): ?array
    {
        if ($courseAssignment?->courseProgram) {
            return [
                'id' => $courseAssignment->courseProgram->id,
                'title' => $courseAssignment->courseProgram->title,
                'placement_level' => $courseAssignment->courseProgram->placement_level,
            ];
        }

        if ($trialClass->student?->studentProfile?->course) {
            return [
                'id' => null,
                'title' => $trialClass->student->studentProfile->course,
                'placement_level' => null,
            ];
        }

        return null;
    }
}
