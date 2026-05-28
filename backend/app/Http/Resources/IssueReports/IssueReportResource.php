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
        $user = $request->user();
        $canViewSensitiveDetails = $user?->hasRole('admin')
            || ($user?->hasRole('staff') && (
                $user->can('issue_reports.view')
                || $user->can('issue_reports.manage')
                || $user->can('issue_reports.resolve')
            ));

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
            'description' => $this->when($canViewSensitiveDetails, $this->resource->description),
            'resolution_notes' => $this->when($canViewSensitiveDetails, $this->resource->resolution_notes),
            'resolved_at' => $this->resource->resolved_at,
            'resolved_by' => $this->resource->resolved_by,
            'reporter' => $this->whenLoaded('reporter', fn () => $this->resource->reporter ? [
                'id' => $this->resource->reporter->id,
                'name' => $this->resource->reporter->name,
                'email' => $this->resource->reporter->email,
            ] : null),
            'assigned_to' => $this->whenLoaded('assignedTo', fn () => $this->resource->assignedTo ? [
                'id' => $this->resource->assignedTo->id,
                'name' => $this->resource->assignedTo->name,
                'email' => $this->resource->assignedTo->email,
            ] : null),
            'comments' => $this->when(
                $canViewSensitiveDetails && $this->resource->relationLoaded('comments'),
                fn () => IssueCommentResource::collection($this->resource->comments)
            ),
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
