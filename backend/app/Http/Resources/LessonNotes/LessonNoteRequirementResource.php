<?php

namespace App\Http\Resources\LessonNotes;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonNoteRequirementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'lesson_id' => $this->resource->id,
            'student_id' => $this->resource->student_id,
            'teacher_id' => $this->resource->teacher_id,
            'status' => $this->resource->status,
            'start_time' => $this->resource->start_time,
            'end_time' => $this->resource->end_time,
            'note_required' => $this->resource->requiresLessonNote(),
            'note_status' => 'missing',
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
        ];
    }
}
