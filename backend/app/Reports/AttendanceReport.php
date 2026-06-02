<?php

namespace App\Reports;

use App\Models\CourseProgramStudentAssignment;
use App\Models\LessonRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AttendanceReport
{
    /**
     * @return array{
     *     summary: array{total_lessons: int, attended_count: int, absent_count: int, cancelled_rescheduled_count: int, attendance_rate: float},
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
    protected function baseQuery(SchoolReportFilters $filters): Builder
    {
        $query = LessonRecord::query()
            ->with([
                'student:id,public_id,name,email',
                'teacher:id,public_id,name,email',
                'student.courseProgramAssignments' => fn ($query) => $query
                    ->active()
                    ->with('courseProgram:id,public_id,title,placement_level')
                    ->latest('start_date')
                    ->latest('assigned_at')
                    ->latest('id'),
            ]);

        $filters->applyTo($query, [
            'date_column' => 'scheduled_date',
            'status_column' => null,
            'course_relation' => 'student.courseProgramAssignments',
        ]);

        $this->applyStatusFilter($query, $filters);

        return $query
            ->orderBy('scheduled_date')
            ->orderBy('start_time')
            ->orderBy('id');
    }

    /**
     * @param  Collection<int, LessonRecord>  $lessonRecords
     * @return array{total_lessons: int, attended_count: int, absent_count: int, cancelled_rescheduled_count: int, attendance_rate: float}
     */
    private function summary(Collection $lessonRecords): array
    {
        $total = $lessonRecords->count();
        $attended = $lessonRecords->filter(fn (LessonRecord $lessonRecord) => $this->isAttended($lessonRecord))->count();
        $absent = $lessonRecords->filter(fn (LessonRecord $lessonRecord) => $this->isAbsent($lessonRecord))->count();
        $cancelledRescheduled = $lessonRecords
            ->whereIn('lesson_status', [LessonRecord::STATUS_CANCELLED, LessonRecord::STATUS_RESCHEDULED])
            ->count();
        $attendanceDenominator = max(0, $total - $cancelledRescheduled);

        return [
            'total_lessons' => $total,
            'attended_count' => $attended,
            'absent_count' => $absent,
            'cancelled_rescheduled_count' => $cancelledRescheduled,
            'attendance_rate' => $attendanceDenominator === 0 ? 0.0 : round(($attended / $attendanceDenominator) * 100, 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(LessonRecord $lessonRecord): array
    {
        $course = $this->course($lessonRecord);

        return [
            'lesson_record_id' => $lessonRecord->public_id,
            'student' => [
                'id' => $lessonRecord->student?->public_id,
                'name' => $lessonRecord->student?->name,
            ],
            'teacher' => [
                'id' => $lessonRecord->teacher?->public_id,
                'name' => $lessonRecord->teacher?->name,
            ],
            'course' => $course,
            'lesson_date' => $lessonRecord->scheduled_date?->toDateString(),
            'start_time' => $lessonRecord->start_time,
            'end_time' => $lessonRecord->end_time,
            'lesson_status' => $lessonRecord->lesson_status,
            'attendance_status' => $lessonRecord->attendance_status,
            'attendance_category' => $this->attendanceCategory($lessonRecord),
            'reason_or_note' => $lessonRecord->internal_remarks ?? $lessonRecord->lesson_notes,
        ];
    }

    protected function applyStatusFilter(Builder $query, SchoolReportFilters $filters): void
    {
        $status = $filters->status();

        if ($status === null) {
            return;
        }

        $query->where(function (Builder $query) use ($status) {
            $query
                ->where('lesson_status', $status)
                ->orWhere('attendance_status', $status);
        });
    }

    protected function isAttended(LessonRecord $lessonRecord): bool
    {
        return in_array($lessonRecord->attendance_status, [
            LessonRecord::ATTENDANCE_PRESENT,
            LessonRecord::ATTENDANCE_LATE,
        ], true);
    }

    protected function isAbsent(LessonRecord $lessonRecord): bool
    {
        return in_array($lessonRecord->attendance_status, [
            LessonRecord::ATTENDANCE_ABSENT,
            LessonRecord::ATTENDANCE_NO_SHOW,
        ], true) || in_array($lessonRecord->lesson_status, [
            LessonRecord::STATUS_MISSED_BY_STUDENT,
            LessonRecord::STATUS_MISSED_BY_TEACHER,
        ], true);
    }

    protected function attendanceCategory(LessonRecord $lessonRecord): string
    {
        if ($this->isAttended($lessonRecord)) {
            return 'attended';
        }

        if ($this->isAbsent($lessonRecord)) {
            return 'absent';
        }

        if (in_array($lessonRecord->lesson_status, [LessonRecord::STATUS_CANCELLED, LessonRecord::STATUS_RESCHEDULED], true)) {
            return $lessonRecord->lesson_status;
        }

        return 'unmarked';
    }

    /**
     * @return array{id: int|null, title: string|null, placement_level: string|null}|null
     */
    protected function course(LessonRecord $lessonRecord): ?array
    {
        /** @var Collection<int, CourseProgramStudentAssignment>|null $assignments */
        $assignments = $lessonRecord->student?->courseProgramAssignments;
        $courseProgram = $assignments?->first()?->courseProgram;

        if ($courseProgram === null) {
            return null;
        }

        return [
            'id' => $courseProgram->public_id,
            'title' => $courseProgram->title,
            'placement_level' => $courseProgram->placement_level,
        ];
    }
}
