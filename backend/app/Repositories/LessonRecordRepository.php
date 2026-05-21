<?php

namespace App\Repositories;

use App\Models\LessonRecord;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class LessonRecordRepository
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<LessonRecord>
     */
    public function paginateForUser(User $user, array $filters): LengthAwarePaginator
    {
        return $this->baseQuery()
            ->when($filters['student_id'] ?? null, fn (Builder $query, int $studentId) => $query->where('student_id', $studentId))
            ->when($filters['teacher_id'] ?? null, fn (Builder $query, int $teacherId) => $query->where('teacher_id', $teacherId))
            ->when($filters['lesson_type'] ?? null, fn (Builder $query, string $lessonType) => $query->where('lesson_type', $lessonType))
            ->when($filters['lesson_status'] ?? null, fn (Builder $query, string $lessonStatus) => $query->where('lesson_status', $lessonStatus))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('scheduled_date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('scheduled_date', '<=', $to))
            ->when(array_key_exists('is_completed', $filters), fn (Builder $query) => $query->where('is_completed', $filters['is_completed']))
            ->when($user->hasRole('teacher') && ! $user->hasAnyRole(['admin', 'staff']), fn (Builder $query) => $query->where('teacher_id', $user->id))
            ->when($user->hasRole('student') && ! $user->hasAnyRole(['admin', 'staff']), fn (Builder $query) => $query->where('student_id', $user->id))
            ->orderByDesc('scheduled_date')
            ->orderByDesc('start_time')
            ->paginate($filters['per_page'] ?? 25);
    }

    public function findWithRelations(LessonRecord $lessonRecord): LessonRecord
    {
        return $lessonRecord->load($this->relations());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): LessonRecord
    {
        return LessonRecord::create($attributes)->load($this->relations());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(LessonRecord $lessonRecord, array $attributes): LessonRecord
    {
        $lessonRecord->fill($attributes)->save();

        return $lessonRecord->refresh()->load($this->relations());
    }

    /**
     * @return Builder<LessonRecord>
     */
    private function baseQuery(): Builder
    {
        return LessonRecord::query()->with($this->relations());
    }

    /**
     * @return array<int, string>
     */
    private function relations(): array
    {
        return [
            'student:id,name,email,timezone',
            'teacher:id,name,email,timezone',
            'completedBy:id,name,email',
            'createdBy:id,name,email',
            'updatedBy:id,name,email',
            'materials:id,title,description,url',
        ];
    }
}
