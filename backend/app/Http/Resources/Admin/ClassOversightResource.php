<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\Concerns\SanitizesApiResponses;
use App\Http\Resources\LessonNotes\LessonNoteResource;
use App\Models\LessonNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassOversightResource extends JsonResource
{
    use SanitizesApiResponses;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lessonNote = $this->resource->lessonNote;
        $lessonRecord = $lessonNote?->lessonRecord;

        return [
            'id' => $this->publicId($this->resource),
            'class_id' => $this->publicId($this->resource),
            'lesson_id' => $this->publicId($this->resource),
            'class_date_time' => [
                'starts_at' => $this->resource->start_time,
                'ends_at' => $this->resource->end_time,
            ],
            'status' => $this->resource->status,
            'lesson_status' => $this->resource->status,
            'attendance_status' => $lessonRecord?->attendance_status,
            'teacher_note_available' => $lessonNote !== null && $lessonNote->submitted_at !== null,
            'teacher_note_id' => $this->publicId($lessonNote) ?? LessonNote::query()->whereKey($lessonNote?->getKey())->value('public_id'),
            'teacher_note_review_status' => $lessonNote?->review_status,
            'issue_count' => $this->resource->issue_reports_count,
            'teacher' => [
                'id' => $this->publicId($this->resource->teacher),
                'name' => $this->resource->teacher?->name,
                'email' => $this->resource->teacher?->email,
                'timezone' => $this->resource->teacher?->timezone,
            ],
            'student' => [
                'id' => $this->publicId($this->resource->student),
                'name' => $this->resource->student?->name,
                'email' => $this->resource->student?->email,
                'timezone' => $this->resource->student?->timezone,
            ],
            'teacher_note' => $this->when($request->boolean('include_teacher_note'), fn () => $lessonNote ? new LessonNoteResource($lessonNote) : null),
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
