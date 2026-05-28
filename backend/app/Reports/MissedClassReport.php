<?php

namespace App\Reports;

use App\Models\LessonRecord;
use Illuminate\Database\Eloquent\Builder;

class MissedClassReport extends AttendanceReport
{
    /**
     * @return array{
     *     summary: array{missed_class_count: int},
     *     rows: list<array<string, mixed>>,
     *     filters: array<string, mixed>
     * }
     */
    public function generate(SchoolReportFilters $filters): array
    {
        $lessonRecords = $this->baseQuery($filters)
            ->where(function (Builder $query) {
                $query
                    ->whereIn('attendance_status', [
                        LessonRecord::ATTENDANCE_ABSENT,
                        LessonRecord::ATTENDANCE_NO_SHOW,
                    ])
                    ->orWhereIn('lesson_status', [
                        LessonRecord::STATUS_MISSED_BY_STUDENT,
                        LessonRecord::STATUS_MISSED_BY_TEACHER,
                    ]);
            })
            ->get();

        return [
            'summary' => [
                'missed_class_count' => $lessonRecords->count(),
            ],
            'rows' => $lessonRecords
                ->map(fn (LessonRecord $lessonRecord) => $this->row($lessonRecord))
                ->values()
                ->all(),
            'filters' => $filters->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(LessonRecord $lessonRecord): array
    {
        return [
            ...parent::row($lessonRecord),
            'missed_status' => $lessonRecord->attendance_status ?? $lessonRecord->lesson_status,
        ];
    }
}
