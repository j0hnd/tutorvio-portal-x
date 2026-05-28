<?php

namespace App\Http\Resources\IssueReports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IssueReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'type' => $this->resource->issue_type,
            'issue_type' => $this->resource->issue_type,
            'status' => $this->resource->status,
            'priority' => $this->resource->priority,
            'reporter_id' => $this->resource->reporter_id,
            'target_user_id' => $this->resource->target_user_id,
            'lesson_id' => $this->resource->lesson_id,
            'class_schedule_id' => $this->resource->class_schedule_id,
            'related_student_id' => $this->resource->related_student_id,
            'related_teacher_id' => $this->resource->related_teacher_id,
            'course_program_id' => $this->resource->course_program_id,
            'material_id' => $this->resource->material_id,
            'learning_resource_id' => $this->resource->learning_resource_id,
            'assigned_to_id' => $this->resource->assigned_to_id,
            'title' => $this->resource->title,
            'description' => $this->resource->description,
            'resolution_notes' => $this->resource->resolution_notes,
            'resolved_at' => $this->resource->resolved_at,
            'resolved_by' => $this->resource->resolved_by,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
