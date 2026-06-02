<?php

namespace App\Http\Resources\LessonNotes;

use App\Http\Resources\Concerns\SanitizesApiResponses;
use App\Http\Resources\LearningResources\LearningResourceResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonNoteResource extends JsonResource
{
    use SanitizesApiResponses;

    /**
     * Transform a lesson note into a public-safe API response.
     *
     * Public fields expose learning progress notes, homework guidance, submitted
     * timing, and loaded lesson/student/teacher/author summaries using public IDs.
     * Internal notes and review metadata are conditional on admin, permitted staff,
     * or the assigned teacher. Created and updated timestamps are admin-only.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->publicId($this->resource),
            'lesson_id' => $this->whenLoaded('lesson', fn () => $this->publicId($this->resource->lesson)),
            'student_id' => $this->whenLoaded('student', fn () => $this->publicId($this->resource->student)),
            'teacher_id' => $this->whenLoaded('teacher', fn () => $this->publicId($this->resource->teacher)),
            'author_id' => $this->whenLoaded('author', fn () => $this->publicId($this->resource->author)),
            'lesson_record_id' => $this->whenLoaded('lessonRecord', fn () => $this->publicId($this->resource->lessonRecord)),
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
            'review_status' => $this->when($this->canViewInternalNote($request->user()), $this->resource->review_status),
            'review_note' => $this->when($this->canViewInternalNote($request->user()), $this->resource->review_note),
            'reviewed_by' => $this->when($this->canViewInternalNote($request->user()), $this->resource->reviewed_by),
            'reviewed_at' => $this->when($this->canViewInternalNote($request->user()), $this->resource->reviewed_at),
            'lesson' => $this->whenLoaded('lesson', fn () => [
                'id' => $this->publicId($this->resource->lesson),
                'status' => $this->resource->lesson->status,
                'start_time' => $this->resource->lesson->start_time,
                'end_time' => $this->resource->lesson->end_time,
                'learning_resources' => $this->resource->lesson->relationLoaded('learningResources')
                    ? LearningResourceResource::collection($this->resource->lesson->learningResources)
                    : null,
            ]),
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->publicId($this->resource->student),
                'name' => $this->resource->student->name,
                'email' => $this->resource->student->email,
                'timezone' => $this->resource->student->timezone,
            ]),
            'teacher' => $this->whenLoaded('teacher', fn () => [
                'id' => $this->publicId($this->resource->teacher),
                'name' => $this->resource->teacher->name,
                'email' => $this->resource->teacher->email,
                'timezone' => $this->resource->teacher->timezone,
            ]),
            'author' => $this->whenLoaded('author', fn () => [
                'id' => $this->publicId($this->resource->author),
                'name' => $this->resource->author?->name,
                'email' => $this->resource->author?->email,
            ]),
            'created_at' => $this->when($this->canViewAdminFields($request), $this->resource->created_at),
            'updated_at' => $this->when($this->canViewAdminFields($request), $this->resource->updated_at),
        ];
    }

    /**
     * Determine whether internal lesson-note fields can be serialized.
     *
     * Admins can view internal notes. Staff need `lesson_notes.view`.
     * Teachers can view internal notes only for notes assigned to them.
     * Students, guests, and unrelated teachers are denied.
     */
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
