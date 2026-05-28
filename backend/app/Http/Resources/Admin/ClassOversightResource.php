<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\LessonNotes\LessonNoteResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassOversightResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lessonNote = $this->resource->lessonNote;
        $lessonRecord = $lessonNote?->lessonRecord;

        return [
            'id' => $this->resource->id,
            'class_id' => $this->resource->id,
            'lesson_id' => $this->resource->id,
            'class_date_time' => [
                'starts_at' => $this->resource->start_time,
                'ends_at' => $this->resource->end_time,
            ],
            'status' => $this->resource->status,
            'lesson_status' => $this->resource->status,
            'attendance_status' => $lessonRecord?->attendance_status,
            'teacher_note_available' => $lessonNote !== null && $lessonNote->submitted_at !== null,
            'teacher_note_id' => $lessonNote?->id,
            'teacher_note_review_status' => $lessonNote?->review_status,
            'issue_count' => $this->resource->issue_reports_count,
            'teacher' => [
                'id' => $this->resource->teacher?->id,
                'name' => $this->resource->teacher?->name,
                'email' => $this->resource->teacher?->email,
                'timezone' => $this->resource->teacher?->timezone,
            ],
            'student' => [
                'id' => $this->resource->student?->id,
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
