<?php

namespace App\Reports;

use App\Models\CourseProgramStudentAssignment;
use App\Models\LessonRecord;
use App\Models\Subscription;
use App\Models\SubscriptionHistory;
use App\Models\TeacherStudentAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RetentionContinuationReport
{
    private const NEARING_END_DAYS = 7;

    /**
     * @return array{
     *     summary: array<string, int|float|null>,
     *     rows: list<array<string, mixed>>,
     *     filters: array<string, mixed>
     * }
     */
    public function generate(SchoolReportFilters $filters): array
    {
        $students = $this->baseQuery($filters)->get();

        return [
            'summary' => $this->summary($students),
            'rows' => $students
                ->map(fn (User $student) => $this->row($student))
                ->values()
                ->all(),
            'filters' => $filters->toArray(),
        ];
    }

    /**
     * @return Builder<User>
     */
    private function baseQuery(SchoolReportFilters $filters): Builder
    {
        return User::query()
            ->role('student')
            ->with([
                'studentProfile.assignedTeacher:id,public_id,name,email,status',
                'studentTeacherAssignments' => fn ($query) => $query
                    ->active()
                    ->with('teacher:id,public_id,name,email,status')
                    ->latest('assigned_at')
                    ->latest('id'),
                'courseProgramAssignments' => fn ($query) => $query
                    ->active()
                    ->with('courseProgram:id,public_id,title,placement_level')
                    ->latest('start_date')
                    ->latest('assigned_at')
                    ->latest('id'),
                'subscriptions' => fn ($query) => $query
                    ->withCount('renewals')
                    ->with(['histories' => fn ($query) => $query
                        ->where('event_type', SubscriptionHistory::EVENT_RENEWED)
                        ->latest('effective_at')
                        ->latest('id')])
                    ->latest('ends_at')
                    ->latest('starts_at')
                    ->latest('id'),
                'studentLessonRecords' => fn ($query) => $query
                    ->where('lesson_status', LessonRecord::STATUS_COMPLETED)
                    ->latest('scheduled_date')
                    ->latest('id'),
            ])
            ->when($filters->dateFrom() || $filters->dateTo(), fn (Builder $query) => $query->whereHas('subscriptions', function (Builder $query) use ($filters) {
                $query
                    ->when($filters->dateFrom(), fn (Builder $query, string $date) => $query->whereDate('ends_at', '>=', $date))
                    ->when($filters->dateTo(), fn (Builder $query, string $date) => $query->whereDate('ends_at', '<=', $date));
            }))
            ->when($filters->teacherId(), fn (Builder $query, int $teacherId) => $query->where(function (Builder $query) use ($teacherId) {
                $query
                    ->whereHas('studentTeacherAssignments', fn (Builder $query) => $query
                        ->active()
                        ->where('teacher_id', $teacherId))
                    ->orWhereHas('studentProfile', fn (Builder $query) => $query
                        ->where('assigned_teacher_id', $teacherId));
            }))
            ->when($filters->studentId(), fn (Builder $query, int $studentId) => $query->whereKey($studentId))
            ->when($filters->courseId(), fn (Builder $query, int $courseId) => $query->whereHas('courseProgramAssignments', fn (Builder $query) => $query
                ->active()
                ->where('course_program_id', $courseId)))
            ->when($filters->status(), fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderBy('name')
            ->orderBy('id');
    }

    /**
     * @param  Collection<int, User>  $students
     * @return array<string, int|float|null>
     */
    private function summary(Collection $students): array
    {
        $today = Carbon::now(config('app.timezone'))->startOfDay();
        $nearingEndThrough = $today->copy()->addDays(self::NEARING_END_DAYS)->endOfDay();
        $continuedStudentsCount = $students->filter(fn (User $student) => $this->hasContinuationEvidence($student))->count();
        $endedPackagesCount = $students->filter(fn (User $student) => $this->hasEndedPackage($student, $today))->count();
        $measurableContinuationCohort = $students
            ->filter(fn (User $student) => $this->hasContinuationEvidence($student) || $this->hasEndedPackage($student, $today))
            ->count();

        return [
            'active_students_count' => $students->where('status', User::STATUS_ACTIVE)->count(),
            'continued_renewed_students_count' => $continuedStudentsCount,
            'students_nearing_package_end_count' => $students
                ->filter(fn (User $student) => $this->currentSubscription($student)?->status === Subscription::STATUS_ACTIVE
                    && $this->currentSubscription($student)?->ends_at !== null
                    && $this->currentSubscription($student)?->ends_at->betweenIncluded($today, $nearingEndThrough))
                ->count(),
            'students_with_ended_packages_count' => $endedPackagesCount,
            'inactive_dropped_students_count' => $students
                ->whereIn('status', [User::STATUS_INACTIVE, User::STATUS_SUSPENDED])
                ->count(),
            'retention_continuation_rate' => $measurableContinuationCohort > 0
                ? round(($continuedStudentsCount / $measurableContinuationCohort) * 100, 2)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(User $student): array
    {
        $subscription = $this->currentSubscription($student);
        $courseAssignment = $this->currentCourseAssignment($student);
        $teacherAssignment = $this->currentTeacherAssignment($student);
        $teacher = $teacherAssignment?->teacher ?? $student->studentProfile?->assignedTeacher;
        $lastLesson = $student->studentLessonRecords->first();

        return [
            'student_id' => $student->public_id,
            'student_name' => $student->name,
            'course' => $this->course($student, $courseAssignment),
            'assigned_teacher' => $teacher ? [
                'id' => $teacher->public_id,
                'name' => $teacher->name,
            ] : null,
            'current_package_status' => $subscription?->is_frozen ? 'frozen' : $subscription?->status,
            'package_end_date' => $subscription?->ends_at?->toDateString(),
            'last_lesson_date' => $lastLesson?->scheduled_date?->toDateString(),
            'renewal_continuation_status' => $this->continuationStatus($subscription),
            'student_status' => $student->status,
        ];
    }

    private function currentSubscription(User $student): ?Subscription
    {
        /** @var Collection<int, Subscription> $subscriptions */
        $subscriptions = $student->subscriptions;

        return $subscriptions->firstWhere('status', Subscription::STATUS_ACTIVE)
            ?? $subscriptions->first();
    }

    private function currentCourseAssignment(User $student): ?CourseProgramStudentAssignment
    {
        /** @var Collection<int, CourseProgramStudentAssignment> $assignments */
        $assignments = $student->courseProgramAssignments;

        return $assignments->first();
    }

    private function currentTeacherAssignment(User $student): ?TeacherStudentAssignment
    {
        /** @var Collection<int, TeacherStudentAssignment> $assignments */
        $assignments = $student->studentTeacherAssignments;

        return $assignments->first();
    }

    /**
     * @return array{id: string|null, title: string|null, placement_level: string|null}|null
     */
    private function course(User $student, ?CourseProgramStudentAssignment $courseAssignment): ?array
    {
        if ($courseAssignment?->courseProgram) {
            return [
                'id' => $courseAssignment->courseProgram->public_id,
                'title' => $courseAssignment->courseProgram->title,
                'placement_level' => $courseAssignment->courseProgram->placement_level,
            ];
        }

        if ($student->studentProfile?->course) {
            return [
                'id' => null,
                'title' => $student->studentProfile->course,
                'placement_level' => null,
            ];
        }

        return null;
    }

    private function continuationStatus(?Subscription $subscription): ?string
    {
        if ($subscription === null) {
            return null;
        }

        if ($subscription->renewed_from_subscription_id !== null || $subscription->histories->isNotEmpty()) {
            return 'renewed';
        }

        if (($subscription->renewals_count ?? 0) > 0) {
            return 'continued';
        }

        if ($subscription->renewal_reminder_status !== Subscription::RENEWAL_REMINDER_STATUS_NONE) {
            return $subscription->renewal_reminder_status;
        }

        return null;
    }

    private function hasContinuationEvidence(User $student): bool
    {
        /** @var Collection<int, Subscription> $subscriptions */
        $subscriptions = $student->subscriptions;

        return $subscriptions->contains(fn (Subscription $subscription) => $subscription->renewed_from_subscription_id !== null
            || ($subscription->renewals_count ?? 0) > 0
            || $subscription->histories->isNotEmpty());
    }

    private function hasEndedPackage(User $student, Carbon $today): bool
    {
        $subscription = $this->currentSubscription($student);

        if ($subscription === null) {
            return false;
        }

        return in_array($subscription->status, [Subscription::STATUS_EXPIRED, Subscription::STATUS_CANCELLED], true)
            || ($subscription->ends_at !== null && $subscription->ends_at->lt($today));
    }
}
