<?php

namespace App\Http\Resources\AcademicRecords;

use App\Http\Resources\Concerns\SanitizesApiResponses;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

class AcademicRecordResource extends JsonResource
{
    use SanitizesApiResponses;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $recordData = $this->resource->data ?? [];

        if (! Gate::allows('viewInternalNotes', $this->resource)) {
            unset($recordData['internal_notes']);
        }

        $canViewInternalNotes = Gate::allows('viewInternalNotes', $this->resource);
        $canViewAdminFields = $this->canViewAdminFields($request);

        $data = [
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
            'student_level' => $recordData['student_level'] ?? null,
            'placement_result' => $recordData['placement_result'] ?? null,
            'course_program_history' => $recordData['course_program_history'] ?? [],
            'attendance_summary' => $recordData['attendance_summary'] ?? [],
            'progress_summary' => $recordData['progress_summary'] ?? null,
            'teacher_remarks' => $this->when($canViewInternalNotes, $recordData['teacher_remarks'] ?? null),
            'certificates' => $recordData['certificates'] ?? [],
            'completion_notes' => $recordData['completion_notes'] ?? null,
            'internal_notes' => $this->when(array_key_exists('internal_notes', $recordData), $recordData['internal_notes'] ?? null),
            'data' => $recordData,
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
        ];

        if ($canViewAdminFields) {
            $data += [
                'recorded_by' => $this->resource->recorded_by,
                'approved_by' => $this->resource->approved_by,
                'approved_at' => $this->resource->approved_at,
                'archived_by' => $this->resource->archived_by,
                'archived_at' => $this->resource->archived_at,
                'recorded_by_user' => $this->whenLoaded('recordedBy', fn () => $this->userSummary($this->resource->recordedBy, $request)),
                'approved_by_user' => $this->whenLoaded('approvedBy', fn () => $this->userSummary($this->resource->approvedBy, $request)),
                'archived_by_user' => $this->whenLoaded('archivedBy', fn () => $this->userSummary($this->resource->archivedBy, $request)),
                'created_at' => $this->resource->created_at,
                'updated_at' => $this->resource->updated_at,
            ];
        }

        return $data;
    }
}
