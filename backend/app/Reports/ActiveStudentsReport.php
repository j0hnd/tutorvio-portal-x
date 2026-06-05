<?php

namespace App\Reports;

use App\Models\CourseProgramStudentAssignment;
use App\Models\Subscription;
use App\Models\TeacherStudentAssignment;
use App\Models\User;
use App\Reports\Concerns\PaginatesReportQueries;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ActiveStudentsReport
{
    use PaginatesReportQueries;

    /**
     * @return array{
     *     summary: array{active_students_count: int},
     *     rows: list<array<string, mixed>>,
     *     filters: array<string, mixed>
     * }
     */
    public function generate(SchoolReportFilters $filters, array $pagination = []): array
    {
        $query = $this->baseQuery($filters);
        $students = (clone $query)->get();
        $page = $this->pageRows($query, $pagination, fn (User $student) => $this->row($student));

        return [
            'summary' => [
                'active_students_count' => $students->count(),
            ],
            'rows' => $page['rows'],
            'total' => $page['total'],
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
            ->where('status', User::STATUS_ACTIVE)
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
                    ->latest('starts_at')
                    ->latest('created_at')
                    ->latest('id'),
            ])
            ->when($filters->dateFrom(), fn (Builder $query, string $date) => $this->whereEnrolledDate($query, '>=', $date))
            ->when($filters->dateTo(), fn (Builder $query, string $date) => $this->whereEnrolledDate($query, '<=', $date))
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

    private function whereEnrolledDate(Builder $query, string $operator, string $date): void
    {
        $query->whereRaw(
            'date(coalesce((select start_date from student_profiles where student_profiles.user_id = users.id limit 1), users.created_at)) '.$operator.' ?',
            [$date]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function row(User $student): array
    {
        /** @var Collection<int, TeacherStudentAssignment> $teacherAssignments */
        $teacherAssignments = $student->studentTeacherAssignments;
        $teacherAssignment = $teacherAssignments->first();
        $teacher = $teacherAssignment?->teacher ?? $student->studentProfile?->assignedTeacher;

        /** @var Collection<int, CourseProgramStudentAssignment> $courseAssignments */
        $courseAssignments = $student->courseProgramAssignments;
        $courseAssignment = $courseAssignments->first();
        $courseProgram = $courseAssignment?->courseProgram;

        /** @var Collection<int, Subscription> $subscriptions */
        $subscriptions = $student->subscriptions;
        $subscription = $subscriptions->firstWhere('status', Subscription::STATUS_ACTIVE) ?? $subscriptions->first();

        return [
            'student_id' => $student->public_id,
            'student_name' => $student->name,
            'course' => $this->course($student, $courseAssignment),
            'assigned_teacher' => $teacher ? [
                'id' => $teacher->public_id,
                'name' => $teacher->name,
            ] : null,
            'enrollment_status' => $courseAssignment?->status,
            'package_status' => $subscription?->status,
            'created_enrolled_date' => $this->createdEnrolledDate($student, $courseAssignment),
            'current_status' => $student->status,
        ];
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

    private function createdEnrolledDate(User $student, ?CourseProgramStudentAssignment $courseAssignment): ?string
    {
        $date = $student->studentProfile?->start_date
            ?? $courseAssignment?->start_date
            ?? $courseAssignment?->assigned_at
            ?? $student->created_at;

        return $date?->toDateString();
    }
}
