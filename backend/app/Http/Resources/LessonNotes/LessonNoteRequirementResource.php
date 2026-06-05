<?php

namespace App\Http\Resources\LessonNotes;

use App\Http\Resources\Concerns\SanitizesApiResponses;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonNoteRequirementResource extends JsonResource
{
    use SanitizesApiResponses;

    /**
     * Transform a lesson-note requirement into a public-safe API response.
     *
     * The response exposes requirement status, due/submission timing, and loaded
     * lesson, student, teacher, or note references using public IDs. It should not
     * expose private lesson-note review fields beyond fields already authorized by
     * the related resource.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->publicId($this->resource),
            'lesson_id' => $this->publicId($this->resource),
            'student_id' => $this->whenLoaded('student', fn () => $this->publicId($this->resource->student)),
            'teacher_id' => $this->whenLoaded('teacher', fn () => $this->publicId($this->resource->teacher)),
            'status' => $this->resource->status,
            'start_time' => $this->resource->start_time,
            'end_time' => $this->resource->end_time,
            'note_required' => $this->resource->requiresLessonNote(),
            'note_status' => 'missing',
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
        ];
    }
}
