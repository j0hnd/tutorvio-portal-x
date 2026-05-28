<?php

namespace App\Reports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SchoolReportFilters
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(private readonly array $filters) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public static function fromArray(array $filters): self
    {
        return new self($filters);
    }

    /**
     * @return array{
     *     date_from?: string,
     *     date_to?: string,
     *     teacher_id?: int,
     *     student_id?: int,
     *     course_id?: int,
     *     status?: string
     * }
     */
    public function toArray(): array
    {
        return array_filter([
            'date_from' => $this->dateFrom(),
            'date_to' => $this->dateTo(),
            'teacher_id' => $this->teacherId(),
            'student_id' => $this->studentId(),
            'course_id' => $this->courseId(),
            'status' => $this->status(),
        ], fn (mixed $value) => $value !== null);
    }

    public function dateFrom(): ?string
    {
        return $this->filters['date_from'] ?? null;
    }

    public function dateTo(): ?string
    {
        return $this->filters['date_to'] ?? null;
    }

    public function teacherId(): ?int
    {
        return $this->integerFilter('teacher_id');
    }

    public function studentId(): ?int
    {
        return $this->integerFilter('student_id');
    }

    public function courseId(): ?int
    {
        return $this->integerFilter('course_id');
    }

    public function status(): ?string
    {
        return $this->filters['status'] ?? null;
    }

    /**
     * Apply common filters to a model query.
     *
     * Supported mapping keys:
     * - date_column: model date/datetime column, default created_at
     * - teacher_column: model teacher id column, default teacher_id
     * - student_column: model student id column, default student_id
     * - course_column: model course id column, default course_program_id
     * - status_column: model status column, default status
     * - course_relation: relationship name for course_id filtering when no course_column exists
     * - course_relation_column: related model column, default course_program_id
     *
     * @param  Builder<Model>  $query
     * @param  array<string, string|null>  $mapping
     * @return Builder<Model>
     */
    public function applyTo(Builder $query, array $mapping = []): Builder
    {
        $dateColumn = $mapping['date_column'] ?? 'created_at';
        $teacherColumn = $mapping['teacher_column'] ?? 'teacher_id';
        $studentColumn = $mapping['student_column'] ?? 'student_id';
        $courseColumn = $mapping['course_column'] ?? 'course_program_id';
        $statusColumn = $mapping['status_column'] ?? 'status';

        $query
            ->when($this->dateFrom(), fn (Builder $query, string $date) => $query->whereDate($dateColumn, '>=', $date))
            ->when($this->dateTo(), fn (Builder $query, string $date) => $query->whereDate($dateColumn, '<=', $date))
            ->when($this->teacherId(), fn (Builder $query, int $id) => $query->where($teacherColumn, $id))
            ->when($this->studentId(), fn (Builder $query, int $id) => $query->where($studentColumn, $id))
            ->when($this->status(), fn (Builder $query, string $status) => $query->where($statusColumn, $status));

        if ($this->courseId() !== null && array_key_exists('course_relation', $mapping)) {
            $relation = $mapping['course_relation'];
            $relationColumn = $mapping['course_relation_column'] ?? 'course_program_id';

            if ($relation !== null) {
                $query->whereHas($relation, fn (Builder $query) => $query->where($relationColumn, $this->courseId()));
            }
        } elseif ($this->courseId() !== null && $courseColumn !== null) {
            $query->where($courseColumn, $this->courseId());
        }

        return $query;
    }

    private function integerFilter(string $key): ?int
    {
        if (! array_key_exists($key, $this->filters)) {
            return null;
        }

        return (int) $this->filters[$key];
    }
}
