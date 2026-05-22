<?php

namespace App\Http\Resources\LessonNotes;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonNoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'lesson_id' => $this->resource->lesson_id,
            'student_id' => $this->resource->student_id,
            'teacher_id' => $this->resource->teacher_id,
            'author_id' => $this->resource->author_id,
            'lesson_record_id' => $this->resource->lesson_record_id,
            'lesson_objective' => $this->resource->lesson_objective,
            'topics_covered' => $this->resource->topics_covered,
            'vocabulary_learned' => $this->resource->vocabulary_learned,
            'grammar_focus' => $this->resource->grammar_focus,
            'pronunciation_issues' => $this->resource->pronunciation_issues,
            'student_speaking_confidence_observation' => $this->resource->student_speaking_confidence_observation,
            'homework_assignment' => $this->resource->homework_assignment,
            'recommendation_for_next_lesson' => $this->resource->recommendation_for_next_lesson,
            'internal_note' => $this->when($this->canViewInternalNote($request->user()), $this->resource->internal_note),
            'submitted_at' => $this->resource->submitted_at,
            'lesson' => $this->whenLoaded('lesson', fn () => [
                'id' => $this->resource->lesson->id,
                'status' => $this->resource->lesson->status,
                'start_time' => $this->resource->lesson->start_time,
                'end_time' => $this->resource->lesson->end_time,
            ]),
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
            'author' => $this->whenLoaded('author', fn () => [
                'id' => $this->resource->author?->id,
                'name' => $this->resource->author?->name,
                'email' => $this->resource->author?->email,
            ]),
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }

    private function canViewInternalNote(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('lesson_notes.view'))
            || ($user->hasRole('teacher') && (int) $this->resource->teacher_id === (int) $user->id);
    }
}
