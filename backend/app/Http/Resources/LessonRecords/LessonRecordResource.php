<?php

namespace App\Http\Resources\LessonRecords;

use App\Http\Resources\Concerns\SanitizesApiResponses;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonRecordResource extends JsonResource
{
    use SanitizesApiResponses;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->resource->id,
            'student_id' => $this->resource->student_id,
            'teacher_id' => $this->resource->teacher_id,
            'scheduled_date' => $this->resource->scheduled_date?->toDateString(),
            'start_time' => $this->resource->start_time,
            'end_time' => $this->resource->end_time,
            'meeting_provider' => $this->resource->meeting_provider,
            'join_available_from' => $this->resource->join_available_from,
            'join_available_until' => $this->resource->join_available_until,
            'is_join_available' => $this->resource->isJoinAvailable(),
            'lesson_type' => $this->resource->lesson_type,
            'lesson_status' => $this->resource->lesson_status,
            'lesson_notes' => $this->resource->lesson_notes,
            'homework_details' => $this->resource->homework_details,
            'homework_instructions' => $this->resource->homework_details,
            'homework_due_date' => $this->resource->homework_due_date?->toDateString(),
            'attendance_status' => $this->resource->attendance_status,
            'is_completed' => $this->resource->is_completed,
            'completed_at' => $this->resource->completed_at,
            'completed_by' => $this->resource->completed_by,
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->resource->student->id,
                'name' => $this->resource->student->name,
                'email' => $this->resource->student->email,
                'timezone' => $this->resource->student->timezone,
            ]),
            'teacher' => $this->whenLoaded('teacher', fn () => [
                'id' => $this->resource->teacher->id,
                'name' => $this->resource->teacher->name,
                'email' => $this->resource->teacher->email,
                'timezone' => $this->resource->teacher->timezone,
            ]),
            'materials' => $this->whenLoaded('materials', fn () => $this->resource->materials->map(fn ($material) => [
                'id' => $material->id,
                'title' => $material->title,
                'description' => $material->description,
                'url' => $material->url,
            ])->values()),
            'lesson_note' => $this->whenLoaded('lessonNote', fn () => $this->lessonNoteSummary($request)),
        ];

        if ($this->canViewAdminFields($request)) {
            $data += [
                'lesson_balance_consumed_subscription_id' => $this->resource->lesson_balance_consumed_subscription_id,
                'lesson_balance_consumed_at' => $this->resource->lesson_balance_consumed_at,
                'created_by' => $this->resource->created_by,
                'updated_by' => $this->resource->updated_by,
                'internal_remarks' => $this->resource->internal_remarks,
                'completed_by_user' => $this->whenLoaded('completedBy', fn () => $this->userSummary($this->resource->completedBy, $request)),
                'created_by_user' => $this->whenLoaded('createdBy', fn () => $this->userSummary($this->resource->createdBy, $request)),
                'updated_by_user' => $this->whenLoaded('updatedBy', fn () => $this->userSummary($this->resource->updatedBy, $request)),
                'created_at' => $this->resource->created_at,
                'updated_at' => $this->resource->updated_at,
            ];
        }

        if ($this->resource->userCanJoinMeeting($request->user())) {
            $data['meeting_link'] = $this->resource->meeting_link;
            $data['meeting_metadata'] = $this->resource->meeting_metadata;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function lessonNoteSummary(Request $request): ?array
    {
        $lessonNote = $this->resource->lessonNote;

        if ($lessonNote === null) {
            return null;
        }

        $data = [
            'id' => $lessonNote->id,
            'lesson_id' => $lessonNote->lesson_id,
            'lesson_record_id' => $lessonNote->lesson_record_id,
            'lesson_objective' => $lessonNote->lesson_objective,
            'topics_covered' => $lessonNote->topics_covered,
            'vocabulary_learned' => $lessonNote->vocabulary_learned,
            'grammar_focus' => $lessonNote->grammar_focus,
            'pronunciation_issues' => $lessonNote->pronunciation_issues,
            'student_speaking_confidence_observation' => $lessonNote->student_speaking_confidence_observation,
            'homework_assignment' => $lessonNote->homework_assignment,
            'recommendation_for_next_lesson' => $lessonNote->recommendation_for_next_lesson,
            'submitted_at' => $lessonNote->submitted_at,
        ];

        if ($this->canViewInternalLessonNote($request)) {
            $data['internal_note'] = $lessonNote->internal_note;
        }

        return $data;
    }

    private function canViewInternalLessonNote(Request $request): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('lesson_notes.view'))
            || ($user->hasRole('teacher') && (int) $this->resource->teacher_id === (int) $user->id);
    }
}
