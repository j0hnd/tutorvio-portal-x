<?php

namespace App\Services;

use App\Models\LessonRecord;
use App\Models\User;
use App\Repositories\LessonRecordRepository;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LessonRecordService
{
    public function __construct(private readonly LessonRecordRepository $lessonRecords) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload, User $actor): LessonRecord
    {
        $this->assertStudentAndTeacherRoles($payload['student_id'], $payload['teacher_id']);

        return DB::transaction(function () use ($payload, $actor): LessonRecord {
            return $this->lessonRecords->create([
                ...Arr::only($payload, $this->mutableFields()),
                ...$this->completionFields($payload, $actor),
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(LessonRecord $lessonRecord, array $payload, User $actor): LessonRecord
    {
        $studentId = $payload['student_id'] ?? $lessonRecord->student_id;
        $teacherId = $payload['teacher_id'] ?? $lessonRecord->teacher_id;
        $this->assertStudentAndTeacherRoles($studentId, $teacherId);

        return DB::transaction(function () use ($lessonRecord, $payload, $actor): LessonRecord {
            return $this->lessonRecords->update($lessonRecord, [
                ...Arr::only($payload, $this->mutableFields()),
                ...$this->completionFields($payload, $actor, $lessonRecord),
                'updated_by' => $actor->id,
            ]);
        });
    }

    public function cancel(LessonRecord $lessonRecord, User $actor, ?string $reason = null): LessonRecord
    {
        $notes = $lessonRecord->lesson_notes;

        if ($reason !== null && $reason !== '') {
            $notes = trim((string) $notes);
            $notes = $notes === '' ? 'Cancellation reason: '.$reason : $notes."\n\nCancellation reason: ".$reason;
        }

        return $this->lessonRecords->update($lessonRecord, [
            'lesson_status' => LessonRecord::STATUS_CANCELLED,
            'is_completed' => false,
            'completed_at' => null,
            'completed_by' => null,
            'lesson_notes' => $notes,
            'updated_by' => $actor->id,
        ]);
    }

    private function assertStudentAndTeacherRoles(int $studentId, int $teacherId): void
    {
        $student = User::findOrFail($studentId);
        $teacher = User::findOrFail($teacherId);

        if (! $student->hasRole('student')) {
            throw ValidationException::withMessages([
                'student_id' => 'The selected user must have the student role.',
            ]);
        }

        if (! $teacher->hasRole('teacher')) {
            throw ValidationException::withMessages([
                'teacher_id' => 'The selected user must have the teacher role.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function completionFields(array $payload, User $actor, ?LessonRecord $lessonRecord = null): array
    {
        $isCompleted = $payload['is_completed'] ?? null;
        $status = $payload['lesson_status'] ?? null;

        if ($isCompleted === null && $status !== LessonRecord::STATUS_COMPLETED) {
            if ($lessonRecord === null) {
                return ['is_completed' => false];
            }

            return [];
        }

        $completed = $isCompleted !== null ? (bool) $isCompleted : $status === LessonRecord::STATUS_COMPLETED;

        if (! $completed) {
            return [
                'is_completed' => false,
                'completed_at' => null,
                'completed_by' => null,
            ];
        }

        return [
            'is_completed' => true,
            'completed_at' => $lessonRecord?->completed_at ?? now(),
            'completed_by' => $lessonRecord?->completed_by ?? $actor->id,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function mutableFields(): array
    {
        return [
            'student_id',
            'teacher_id',
            'scheduled_date',
            'start_time',
            'end_time',
            'meeting_link',
            'lesson_type',
            'lesson_status',
            'lesson_notes',
            'homework_details',
            'is_completed',
        ];
    }
}
