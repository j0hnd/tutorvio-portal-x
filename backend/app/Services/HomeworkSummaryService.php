<?php

namespace App\Services;

use App\Models\Homework;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class HomeworkSummaryService
{
    /**
     * Summarize homework status counts and trends for filtered assignments.
     *
     * The filters are expected to be validated before this service is called.
     * This method only reads homework data and does not update overdue status.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function summary(array $filters): array
    {
        $query = $this->filteredQuery($filters);
        $totalAssigned = (clone $query)->count();
        $completedCount = (clone $query)->where('status', Homework::STATUS_COMPLETED)->count();
        $reviewedCount = (clone $query)->where('status', Homework::STATUS_REVIEWED)->count();
        $overdueCount = $this->overdueQuery(clone $query)->count();

        return [
            'filters' => [
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
                'teacher_id' => isset($filters['teacher_id']) ? (int) $filters['teacher_id'] : null,
                'student_id' => isset($filters['student_id']) ? (int) $filters['student_id'] : null,
                'status' => $filters['status'] ?? null,
                'course' => $filters['course'] ?? null,
                'level' => $filters['level'] ?? null,
            ],
            'summary' => [
                'total_assigned' => $totalAssigned,
                'assigned_count' => (clone $query)->where('status', Homework::STATUS_ASSIGNED)->count(),
                'in_progress_count' => (clone $query)->where('status', Homework::STATUS_IN_PROGRESS)->count(),
                'completed_count' => $completedCount,
                'reviewed_count' => $reviewedCount,
                'overdue_count' => $overdueCount,
                'completion_rate' => $this->rate($completedCount + $reviewedCount, $totalAssigned),
                'overdue_rate' => $this->rate($overdueCount, $totalAssigned),
            ],
            'trends' => $this->trends($query),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Homework>
     */
    private function filteredQuery(array $filters): Builder
    {
        return Homework::query()
            ->when(isset($filters['date_from']), function (Builder $query) use ($filters): void {
                $query->whereDate('created_at', '>=', $filters['date_from']);
            })
            ->when(isset($filters['date_to']), function (Builder $query) use ($filters): void {
                $query->whereDate('created_at', '<=', $filters['date_to']);
            })
            ->when(isset($filters['teacher_id']), function (Builder $query) use ($filters): void {
                $query->where('teacher_id', $filters['teacher_id']);
            })
            ->when(isset($filters['student_id']), function (Builder $query) use ($filters): void {
                $query->where('student_id', $filters['student_id']);
            })
            ->when(isset($filters['status']), function (Builder $query) use ($filters): void {
                $query->where('status', $filters['status']);
            })
            ->when(isset($filters['course']), function (Builder $query) use ($filters): void {
                $query->whereHas('student.studentProfile', fn (Builder $query) => $query->where('course', $filters['course']));
            })
            ->when(isset($filters['level']), function (Builder $query) use ($filters): void {
                $query->whereHas('student.studentProfile', function (Builder $query) use ($filters): void {
                    $query->where(function (Builder $query) use ($filters): void {
                        $query->where('current_level', $filters['level'])
                            ->orWhere('english_level', $filters['level']);
                    });
                });
            });
    }

    /**
     * @param  Builder<Homework>  $query
     * @return Builder<Homework>
     */
    private function overdueQuery(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->where('status', Homework::STATUS_OVERDUE)
                ->orWhere(function (Builder $query): void {
                    $query->whereDate('due_date', '<', now()->toDateString())
                        ->whereNotIn('status', [Homework::STATUS_COMPLETED, Homework::STATUS_REVIEWED]);
                });
        });
    }

    /**
     * @param  Builder<Homework>  $query
     * @return array<int, array<string, mixed>>
     */
    private function trends(Builder $query): array
    {
        return (clone $query)
            ->selectRaw('DATE(created_at) as date')
            ->selectRaw('COUNT(*) as total_assigned')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as in_progress_count', [Homework::STATUS_IN_PROGRESS])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed_count', [Homework::STATUS_COMPLETED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as reviewed_count', [Homework::STATUS_REVIEWED])
            ->selectRaw(
                'SUM(CASE WHEN status = ? OR (due_date < ? AND status NOT IN (?, ?)) THEN 1 ELSE 0 END) as overdue_count',
                [
                    Homework::STATUS_OVERDUE,
                    now()->toDateString(),
                    Homework::STATUS_COMPLETED,
                    Homework::STATUS_REVIEWED,
                ],
            )
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->map(function (Homework $row): array {
                $totalAssigned = (int) $row->total_assigned;
                $completedCount = (int) $row->completed_count;
                $reviewedCount = (int) $row->reviewed_count;
                $overdueCount = (int) $row->overdue_count;

                return [
                    'date' => $row->date,
                    'total_assigned' => $totalAssigned,
                    'in_progress_count' => (int) $row->in_progress_count,
                    'completed_count' => $completedCount,
                    'reviewed_count' => $reviewedCount,
                    'overdue_count' => $overdueCount,
                    'completion_rate' => $this->rate($completedCount + $reviewedCount, $totalAssigned),
                    'overdue_rate' => $this->rate($overdueCount, $totalAssigned),
                ];
            })
            ->values()
            ->all();
    }

    private function rate(int $count, int $total): float
    {
        if ($total === 0) {
            return 0.0;
        }

        return round($count / $total, 4);
    }
}
