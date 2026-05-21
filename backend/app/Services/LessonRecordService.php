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
            $lessonRecord = $this->lessonRecords->create([
                ...Arr::only($payload, $this->mutableFields()),
                ...$this->completionFields($payload, $actor),
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            return $this->syncMaterials($lessonRecord, $payload);
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
            $updatedLessonRecord = $this->lessonRecords->update($lessonRecord, [
                ...Arr::only($payload, $this->mutableFields()),
                ...$this->completionFields($payload, $actor, $lessonRecord),
                'updated_by' => $actor->id,
            ]);

            return $this->syncMaterials($updatedLessonRecord, $payload);
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
        $hasCompletion = array_key_exists('is_completed', $payload);
        $hasStatus = array_key_exists('lesson_status', $payload);
        $isCompleted = $hasCompletion ? (bool) $payload['is_completed'] : null;
        $status = $hasStatus ? $payload['lesson_status'] : null;

        if (! $hasCompletion && $status !== LessonRecord::STATUS_COMPLETED) {
            if ($lessonRecord === null) {
                return ['is_completed' => false];
            }

            return [];
        }

        $completed = $isCompleted ?? $status === LessonRecord::STATUS_COMPLETED;

        if (! $completed) {
            $fields = [
                'is_completed' => false,
                'completed_at' => null,
                'completed_by' => null,
            ];

            if ($status === LessonRecord::STATUS_COMPLETED || (! $hasStatus && $lessonRecord?->lesson_status === LessonRecord::STATUS_COMPLETED)) {
                $fields['lesson_status'] = LessonRecord::STATUS_SCHEDULED;
            }

            return $fields;
        }

        return [
            'is_completed' => true,
            'lesson_status' => LessonRecord::STATUS_COMPLETED,
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
            'meeting_provider',
            'meeting_metadata',
            'join_available_from',
            'join_available_until',
            'lesson_type',
            'lesson_status',
            'lesson_notes',
            'homework_details',
            'homework_due_date',
            'attendance_status',
            'is_completed',
            'internal_remarks',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncMaterials(LessonRecord $lessonRecord, array $payload): LessonRecord
    {
        if (array_key_exists('material_ids', $payload)) {
            $lessonRecord->materials()->sync($payload['material_ids'] ?? []);
        }

        return $lessonRecord->refresh()->load([
            'student:id,name,email,timezone',
            'teacher:id,name,email,timezone',
            'completedBy:id,name,email',
            'createdBy:id,name,email',
            'updatedBy:id,name,email',
            'materials:id,title,description,url',
        ]);
    }
}
