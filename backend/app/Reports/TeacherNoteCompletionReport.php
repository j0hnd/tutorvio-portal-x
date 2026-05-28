<?php

namespace App\Reports;

use App\Models\CourseProgramStudentAssignment;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TeacherNoteCompletionReport
{
    /**
     * @return array{
     *     summary: array{total_completed_lessons_requiring_notes: int, lessons_with_teacher_notes: int, lessons_missing_teacher_notes: int, teacher_note_completion_rate: float},
     *     rows: list<array<string, mixed>>,
     *     filters: array<string, mixed>
     * }
     */
    public function generate(SchoolReportFilters $filters, User $user): array
    {
        $lessons = $this->baseQuery($filters, $user)->get();

        return [
            'summary' => $this->summary($lessons),
            'rows' => $lessons
                ->map(fn (Lesson $lesson) => $this->row($lesson))
                ->values()
                ->all(),
            'filters' => $filters->toArray(),
        ];
    }

    /**
     * @return Builder<Lesson>
     */
    private function baseQuery(SchoolReportFilters $filters, User $user): Builder
    {
        $query = Lesson::query()
            ->requiringLessonNote()
            ->with([
                'lessonNote:id,lesson_id,submitted_at',
                'student:id,name,email',
                'teacher:id,name,email',
                'student.courseProgramAssignments' => fn ($query) => $query
                    ->active()
                    ->with('courseProgram:id,title,placement_level')
                    ->latest('start_date')
                    ->latest('assigned_at')
                    ->latest('id'),
            ])
            ->when(
                $user->hasRole('teacher') && ! $user->hasAnyRole(['admin', 'staff']),
                fn (Builder $query) => $query->where('teacher_id', $user->id)
            );

        $filters->applyTo($query, [
            'date_column' => 'start_time',
            'status_column' => 'status',
            'course_relation' => 'student.courseProgramAssignments',
        ]);

        return $query
            ->orderBy('start_time')
            ->orderBy('id');
    }

    /**
     * @param  Collection<int, Lesson>  $lessons
     * @return array{total_completed_lessons_requiring_notes: int, lessons_with_teacher_notes: int, lessons_missing_teacher_notes: int, teacher_note_completion_rate: float}
     */
    private function summary(Collection $lessons): array
    {
        $total = $lessons->count();
        $withNotes = $lessons->filter(fn (Lesson $lesson) => $this->hasSubmittedTeacherNote($lesson))->count();

        return [
            'total_completed_lessons_requiring_notes' => $total,
            'lessons_with_teacher_notes' => $withNotes,
            'lessons_missing_teacher_notes' => $total - $withNotes,
            'teacher_note_completion_rate' => $total === 0 ? 0.0 : round(($withNotes / $total) * 100, 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Lesson $lesson): array
    {
        $course = $this->course($lesson);
        $submittedAt = $lesson->lessonNote?->submitted_at;

        return [
            'lesson_id' => $lesson->id,
            'lesson_datetime' => $lesson->start_time?->toJSON(),
            'lesson_date' => $lesson->start_time?->toDateString(),
            'start_time' => $lesson->start_time?->format('H:i:s'),
            'end_time' => $lesson->end_time?->format('H:i:s'),
            'teacher' => [
                'id' => $lesson->teacher?->id,
                'name' => $lesson->teacher?->name,
            ],
            'student' => [
                'id' => $lesson->student?->id,
                'name' => $lesson->student?->name,
            ],
            'course' => $course,
            'lesson_status' => $lesson->status,
            'note_status' => $this->hasSubmittedTeacherNote($lesson) ? 'submitted' : 'missing',
            'note_submitted_at' => $submittedAt?->toJSON(),
        ];
    }

    private function hasSubmittedTeacherNote(Lesson $lesson): bool
    {
        return $lesson->lessonNote?->submitted_at !== null;
    }

    /**
     * @return array{id: int|null, title: string|null, placement_level: string|null}|null
     */
    private function course(Lesson $lesson): ?array
    {
        /** @var Collection<int, CourseProgramStudentAssignment>|null $assignments */
        $assignments = $lesson->student?->courseProgramAssignments;
        $courseProgram = $assignments?->first()?->courseProgram;

        if ($courseProgram === null) {
            return null;
        }

        return [
            'id' => $courseProgram->id,
            'title' => $courseProgram->title,
            'placement_level' => $courseProgram->placement_level,
        ];
    }
}
