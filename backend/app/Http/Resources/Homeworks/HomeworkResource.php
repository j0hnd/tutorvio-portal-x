<?php

namespace App\Http\Resources\Homeworks;

use App\Http\Resources\Concerns\SanitizesApiResponses;
use App\Http\Resources\LearningResources\LearningResourceResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HomeworkResource extends JsonResource
{
    use SanitizesApiResponses;

    /**
     * Transform homework into a public-safe API response.
     *
     * Public fields expose assignment instructions, status, feedback, attachments,
     * and loaded lesson/student/teacher summaries using public IDs. Created and
     * updated timestamps are admin-only. Database primary keys should not be
     * exposed in frontend-facing references.
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
            'title' => $this->resource->title,
            'instructions' => $this->resource->instructions,
            'due_date' => $this->resource->due_date?->toDateString(),
            'status' => $this->resource->status,
            'teacher_feedback' => $this->resource->teacher_feedback,
            'completed_at' => $this->resource->completed_at,
            'reviewed_at' => $this->resource->reviewed_at,
            'links' => $this->resource->attachment_links ?? [],
            'attachment_links' => $this->resource->attachment_links ?? [],
            'documents' => $this->whenLoaded(
                'learningResources',
                fn () => LearningResourceResource::collection($this->resource->learningResources)
            ),
            'lesson' => $this->whenLoaded('lesson', fn () => [
                'id' => $this->publicId($this->resource->lesson),
                'student_id' => $this->whenLoaded('student', fn () => $this->publicId($this->resource->student)),
                'teacher_id' => $this->whenLoaded('teacher', fn () => $this->publicId($this->resource->teacher)),
                'status' => $this->resource->lesson->status,
                'start_time' => $this->resource->lesson->start_time,
                'end_time' => $this->resource->lesson->end_time,
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
            'created_at' => $this->when($this->canViewAdminFields($request), $this->resource->created_at),
            'updated_at' => $this->when($this->canViewAdminFields($request), $this->resource->updated_at),
        ];
    }
}
