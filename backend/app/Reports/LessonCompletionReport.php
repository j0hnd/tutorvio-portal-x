<?php

namespace App\Reports;

use App\Models\CourseProgramStudentAssignment;
use App\Models\LessonRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LessonCompletionReport
{
    /**
     * @return array{
     *     summary: array{total_lessons: int, completed_count: int, pending_upcoming_count: int, cancelled_rescheduled_missed_count: int, completion_rate: float},
     *     rows: list<array<string, mixed>>,
     *     filters: array<string, mixed>
     * }
     */
    public function generate(SchoolReportFilters $filters): array
    {
        $lessonRecords = $this->baseQuery($filters)->get();

        return [
            'summary' => $this->summary($lessonRecords),
            'rows' => $lessonRecords
                ->map(fn (LessonRecord $lessonRecord) => $this->row($lessonRecord))
                ->values()
                ->all(),
            'filters' => $filters->toArray(),
        ];
    }

    /**
     * @return Builder<LessonRecord>
     */
    private function baseQuery(SchoolReportFilters $filters): Builder
    {
        $query = LessonRecord::query()
            ->with([
                'student:id,name,email',
                'teacher:id,name,email',
                'student.courseProgramAssignments' => fn ($query) => $query
                    ->active()
                    ->with('courseProgram:id,title,placement_level')
                    ->latest('start_date')
                    ->latest('assigned_at')
                    ->latest('id'),
            ]);

        $filters->applyTo($query, [
            'date_column' => 'scheduled_date',
            'status_column' => 'lesson_status',
            'course_relation' => 'student.courseProgramAssignments',
        ]);

        return $query
            ->orderBy('scheduled_date')
            ->orderBy('start_time')
            ->orderBy('id');
    }

    /**
     * @param  Collection<int, LessonRecord>  $lessonRecords
     * @return array{total_lessons: int, completed_count: int, pending_upcoming_count: int, cancelled_rescheduled_missed_count: int, completion_rate: float}
     */
    private function summary(Collection $lessonRecords): array
    {
        $total = $lessonRecords->count();
        $completed = $lessonRecords->filter(fn (LessonRecord $lessonRecord) => $this->isCompleted($lessonRecord))->count();

        return [
            'total_lessons' => $total,
            'completed_count' => $completed,
            'pending_upcoming_count' => $lessonRecords
                ->whereIn('lesson_status', [
                    LessonRecord::STATUS_SCHEDULED,
                    LessonRecord::STATUS_PENDING_CONFIRMATION,
                ])
                ->count(),
            'cancelled_rescheduled_missed_count' => $lessonRecords
                ->filter(fn (LessonRecord $lessonRecord) => $this->isCancelledRescheduledOrMissed($lessonRecord))
                ->count(),
            'completion_rate' => $total === 0 ? 0.0 : round(($completed / $total) * 100, 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(LessonRecord $lessonRecord): array
    {
        $course = $this->course($lessonRecord);

        return [
            'lesson_id' => $lessonRecord->id,
            'lesson_record_id' => $lessonRecord->id,
            'lesson_datetime' => $lessonRecord->scheduled_date?->toDateString().' '.$lessonRecord->start_time,
            'lesson_date' => $lessonRecord->scheduled_date?->toDateString(),
            'start_time' => $lessonRecord->start_time,
            'end_time' => $lessonRecord->end_time,
            'student' => [
                'id' => $lessonRecord->student?->id,
                'name' => $lessonRecord->student?->name,
            ],
            'teacher' => [
                'id' => $lessonRecord->teacher?->id,
                'name' => $lessonRecord->teacher?->name,
            ],
            'course' => $course,
            'lesson_type' => $lessonRecord->lesson_type,
            'lesson_status' => $lessonRecord->lesson_status,
            'completion_status' => $this->isCompleted($lessonRecord) ? 'completed' : 'not_completed',
            'completed_at' => $lessonRecord->completed_at?->toJSON(),
        ];
    }

    private function isCompleted(LessonRecord $lessonRecord): bool
    {
        return $lessonRecord->lesson_status === LessonRecord::STATUS_COMPLETED || $lessonRecord->is_completed;
    }

    private function isCancelledRescheduledOrMissed(LessonRecord $lessonRecord): bool
    {
        return in_array($lessonRecord->lesson_status, [
            LessonRecord::STATUS_CANCELLED,
            LessonRecord::STATUS_RESCHEDULED,
            LessonRecord::STATUS_MISSED_BY_STUDENT,
            LessonRecord::STATUS_MISSED_BY_TEACHER,
        ], true) || in_array($lessonRecord->attendance_status, [
            LessonRecord::ATTENDANCE_ABSENT,
            LessonRecord::ATTENDANCE_NO_SHOW,
        ], true);
    }

    /**
     * @return array{id: int|null, title: string|null, placement_level: string|null}|null
     */
    private function course(LessonRecord $lessonRecord): ?array
    {
        /** @var Collection<int, CourseProgramStudentAssignment>|null $assignments */
        $assignments = $lessonRecord->student?->courseProgramAssignments;
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
