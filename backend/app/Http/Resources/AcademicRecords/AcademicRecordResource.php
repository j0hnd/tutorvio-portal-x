<?php

namespace App\Http\Resources\AcademicRecords;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

class AcademicRecordResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->resource->data ?? [];

        if (! Gate::allows('viewInternalNotes', $this->resource)) {
            unset($data['internal_notes']);
        }

        return [
            'id' => $this->resource->id,
            'student_id' => $this->resource->student_id,
            'teacher_id' => $this->resource->teacher_id,
            'course_program_id' => $this->resource->course_program_id,
            'lesson_id' => $this->resource->lesson_id,
            'class_schedule_id' => $this->resource->class_schedule_id,
            'record_type' => $this->resource->record_type,
            'title' => $this->resource->title,
            'description' => $this->resource->description,
            'status' => $this->resource->status,
            'recorded_on' => $this->resource->recorded_on?->toDateString(),
            'student_level' => $data['student_level'] ?? null,
            'placement_result' => $data['placement_result'] ?? null,
            'course_program_history' => $data['course_program_history'] ?? [],
            'attendance_summary' => $data['attendance_summary'] ?? [],
            'progress_summary' => $data['progress_summary'] ?? null,
            'teacher_remarks' => $data['teacher_remarks'] ?? null,
            'certificates' => $data['certificates'] ?? [],
            'completion_notes' => $data['completion_notes'] ?? null,
            'internal_notes' => $this->when(array_key_exists('internal_notes', $data), $data['internal_notes'] ?? null),
            'data' => $data,
            'recorded_by' => $this->resource->recorded_by,
            'approved_by' => $this->resource->approved_by,
            'approved_at' => $this->resource->approved_at,
            'archived_by' => $this->resource->archived_by,
            'archived_at' => $this->resource->archived_at,
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->resource->student->id,
                'name' => $this->resource->student->name,
                'email' => $this->resource->student->email,
                'timezone' => $this->resource->student->timezone,
            ]),
            'teacher' => $this->whenLoaded('teacher', fn () => $this->resource->teacher === null ? null : [
                'id' => $this->resource->teacher->id,
                'name' => $this->resource->teacher->name,
                'email' => $this->resource->teacher->email,
                'timezone' => $this->resource->teacher->timezone,
            ]),
            'course_program' => $this->whenLoaded('courseProgram', fn () => $this->resource->courseProgram === null ? null : [
                'id' => $this->resource->courseProgram->id,
                'title' => $this->resource->courseProgram->title,
                'slug' => $this->resource->courseProgram->slug,
                'placement_level' => $this->resource->courseProgram->placement_level,
            ]),
            'recorded_by_user' => $this->whenLoaded('recordedBy', fn () => $this->resource->recordedBy === null ? null : [
                'id' => $this->resource->recordedBy->id,
                'name' => $this->resource->recordedBy->name,
                'email' => $this->resource->recordedBy->email,
            ]),
            'approved_by_user' => $this->whenLoaded('approvedBy', fn () => $this->resource->approvedBy === null ? null : [
                'id' => $this->resource->approvedBy->id,
                'name' => $this->resource->approvedBy->name,
                'email' => $this->resource->approvedBy->email,
            ]),
            'archived_by_user' => $this->whenLoaded('archivedBy', fn () => $this->resource->archivedBy === null ? null : [
                'id' => $this->resource->archivedBy->id,
                'name' => $this->resource->archivedBy->name,
                'email' => $this->resource->archivedBy->email,
            ]),
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
