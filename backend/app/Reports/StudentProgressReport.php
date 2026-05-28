<?php

namespace App\Reports;

use App\Models\CourseProgramStudentAssignment;
use App\Models\StudentProgressRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class StudentProgressReport
{
    /**
     * @return array{
     *     summary: array<string, mixed>,
     *     rows: list<array<string, mixed>>,
     *     filters: array<string, mixed>
     * }
     */
    public function generate(SchoolReportFilters $filters, User $actor): array
    {
        $records = $this->baseQuery($filters, $actor)->get();
        $rows = $records
            ->groupBy('student_id')
            ->map(fn (Collection $studentRecords) => $this->row($studentRecords))
            ->sortBy(fn (array $row) => $row['student_name'] ?? '')
            ->values();

        return [
            'summary' => $this->summary($records, $rows),
            'rows' => $rows->all(),
            'filters' => $filters->toArray(),
        ];
    }

    /**
     * @return Builder<StudentProgressRecord>
     */
    private function baseQuery(SchoolReportFilters $filters, User $actor): Builder
    {
        $query = StudentProgressRecord::query()
            ->with([
                'student:id,name,email,timezone,status',
                'student.studentProfile:id,user_id,current_level,english_level,course,assigned_teacher_id',
                'student.studentProfile.assignedTeacher:id,name,email',
                'student.courseProgramAssignments' => fn ($query) => $query
                    ->active()
                    ->with('courseProgram:id,title,placement_level')
                    ->latest('start_date')
                    ->latest('assigned_at')
                    ->latest('id'),
                'teacher:id,name,email',
            ]);

        $filters->applyTo($query, [
            'date_column' => 'recorded_at',
            'status_column' => 'progress_status',
            'course_relation' => 'student.courseProgramAssignments',
        ]);

        $this->applyVisibility($query, $actor);

        return $query
            ->orderByDesc('recorded_at')
            ->orderByDesc('id');
    }

    /**
     * @param  Builder<StudentProgressRecord>  $query
     */
    private function applyVisibility(Builder $query, User $actor): void
    {
        if ($actor->hasRole('admin') || ($actor->hasRole('staff') && $this->staffCanViewReports($actor))) {
            return;
        }

        if ($actor->hasRole('student')) {
            $query->where('student_id', $actor->id);

            return;
        }

        if ($actor->hasRole('teacher')) {
            $query->whereHas(
                'student.studentProfile',
                fn (Builder $query) => $query->where('assigned_teacher_id', $actor->id)
            );
        }
    }

    private function staffCanViewReports(User $actor): bool
    {
        return $actor->can('school_reports.view') || $actor->can('student_progress_records.view');
    }

    /**
     * @param  Collection<int, StudentProgressRecord>  $records
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function summary(Collection $records, Collection $rows): array
    {
        return [
            'students_count' => $rows->count(),
            'progress_records_count' => $records->count(),
            'completed_lessons_count' => $rows->sum('completed_lessons_count'),
            'teacher_comments_count' => $records->filter(fn (StudentProgressRecord $record) => ! blank($record->teacher_comments))->count(),
            'status_counts' => $records
                ->countBy('progress_status')
                ->sortKeys()
                ->all(),
            'students_by_status' => $rows
                ->countBy('current_progress_status')
                ->filter(fn (int $count, mixed $status) => $status !== null)
                ->sortKeys()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, StudentProgressRecord>  $records
     * @return array<string, mixed>
     */
    private function row(Collection $records): array
    {
        /** @var StudentProgressRecord $latest */
        $latest = $records->first();
        $student = $latest->student;
        $courseAssignment = $student?->courseProgramAssignments?->first();
        $assignedTeacher = $student?->studentProfile?->assignedTeacher ?? $latest->teacher;

        return [
            'student_id' => $student?->id,
            'student_name' => $student?->name,
            'student' => $student ? [
                'id' => $student->id,
                'name' => $student->name,
            ] : null,
            'course' => $this->course($student, $courseAssignment),
            'assigned_teacher' => $assignedTeacher ? [
                'id' => $assignedTeacher->id,
                'name' => $assignedTeacher->name,
            ] : null,
            'progress_level' => $student?->studentProfile?->current_level,
            'previous_level' => $student?->studentProfile?->english_level,
            'milestone' => $this->latestMilestone($records),
            'skill_progress' => $this->skillProgress($records),
            'teacher_comments_count' => $records->filter(fn (StudentProgressRecord $record) => ! blank($record->teacher_comments))->count(),
            'completed_lessons_count' => (int) ($latest->lesson_completion_count ?? 0),
            'last_progress_update_date' => $latest->recorded_at?->toDateString(),
            'current_progress_status' => $latest->progress_status,
        ];
    }

    /**
     * @return array{id: int|null, title: string|null, placement_level: string|null}|null
     */
    private function course(?User $student, ?CourseProgramStudentAssignment $courseAssignment): ?array
    {
        if ($courseAssignment?->courseProgram) {
            return [
                'id' => $courseAssignment->courseProgram->id,
                'title' => $courseAssignment->courseProgram->title,
                'placement_level' => $courseAssignment->courseProgram->placement_level,
            ];
        }

        if ($student?->studentProfile?->course) {
            return [
                'id' => null,
                'title' => $student->studentProfile->course,
                'placement_level' => null,
            ];
        }

        return null;
    }

    /**
     * @param  Collection<int, StudentProgressRecord>  $records
     */
    private function latestMilestone(Collection $records): ?string
    {
        return $records
            ->flatMap(fn (StudentProgressRecord $record) => $record->milestone_achievements ?? [])
            ->first(fn (mixed $milestone) => is_string($milestone) && ! blank($milestone));
    }

    /**
     * @param  Collection<int, StudentProgressRecord>  $records
     * @return array<string, array<string, mixed>>
     */
    private function skillProgress(Collection $records): array
    {
        $progress = [];

        foreach ($records as $record) {
            foreach ($record->progress_summary_by_skill ?? [] as $skillArea => $summary) {
                if (blank($summary) || array_key_exists($skillArea, $progress)) {
                    continue;
                }

                $progress[$skillArea] = [
                    'summary' => $summary,
                    'record_id' => $record->id,
                    'recorded_at' => $record->recorded_at?->toDateString(),
                    'status' => $record->progress_status,
                ];
            }
        }

        return $progress;
    }
}
