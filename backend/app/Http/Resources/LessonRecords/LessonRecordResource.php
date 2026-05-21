<?php

namespace App\Http\Resources\LessonRecords;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonRecordResource extends JsonResource
{
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
            'meeting_link' => $this->resource->meeting_link,
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
            'created_by' => $this->resource->created_by,
            'updated_by' => $this->resource->updated_by,
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
            'completed_by_user' => $this->whenLoaded('completedBy', fn () => [
                'id' => $this->resource->completedBy?->id,
                'name' => $this->resource->completedBy?->name,
                'email' => $this->resource->completedBy?->email,
            ]),
            'created_by_user' => $this->whenLoaded('createdBy', fn () => [
                'id' => $this->resource->createdBy?->id,
                'name' => $this->resource->createdBy?->name,
                'email' => $this->resource->createdBy?->email,
            ]),
            'updated_by_user' => $this->whenLoaded('updatedBy', fn () => [
                'id' => $this->resource->updatedBy?->id,
                'name' => $this->resource->updatedBy?->name,
                'email' => $this->resource->updatedBy?->email,
            ]),
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];

        if ($request->user()?->hasAnyRole(['admin', 'staff'])) {
            $data['internal_remarks'] = $this->resource->internal_remarks;
        }

        return $data;
    }
}
