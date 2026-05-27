<?php

namespace App\Http\Resources\TeacherAssignments;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherStudentAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'student_id' => $this->resource->student_id,
            'teacher_id' => $this->resource->teacher_id,
            'assigned_by' => $this->resource->assigned_by,
            'assigned_at' => $this->resource->assigned_at,
            'status' => $this->resource->status,
            'reason' => $this->resource->reason,
            'notes' => $this->resource->notes,
            'ended_at' => $this->resource->ended_at,
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->resource->student->id,
                'name' => $this->resource->student->name,
                'email' => $this->resource->student->email,
                'status' => $this->resource->student->status,
            ]),
            'teacher' => $this->whenLoaded('teacher', fn () => [
                'id' => $this->resource->teacher->id,
                'name' => $this->resource->teacher->name,
                'email' => $this->resource->teacher->email,
                'status' => $this->resource->teacher->status,
            ]),
            'assigned_by_user' => $this->whenLoaded('assignedBy', fn () => [
                'id' => $this->resource->assignedBy?->id,
                'name' => $this->resource->assignedBy?->name,
                'email' => $this->resource->assignedBy?->email,
                'status' => $this->resource->assignedBy?->status,
            ]),
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
