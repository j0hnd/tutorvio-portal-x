<?php

namespace App\Http\Resources\Homeworks;

use App\Http\Resources\LearningResources\LearningResourceResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HomeworkResource extends JsonResource
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
                'id' => $this->resource->lesson->id,
                'student_id' => $this->resource->lesson->student_id,
                'teacher_id' => $this->resource->lesson->teacher_id,
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
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
