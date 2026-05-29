<?php

namespace App\Http\Resources\TeacherAssignments;

use App\Http\Resources\Concerns\SanitizesApiResponses;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherStudentAssignmentResource extends JsonResource
{
    use SanitizesApiResponses;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canViewAdminFields = $this->canViewAdminFields($request, 'teacher_student_assignments.view');

        $data = [
            'id' => $this->resource->id,
            'student_id' => $this->resource->student_id,
            'teacher_id' => $this->resource->teacher_id,
            'assigned_at' => $this->resource->assigned_at,
            'status' => $this->resource->status,
            'reason' => $this->resource->reason,
            'ended_at' => $this->resource->ended_at,
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->resource->student->id,
                'name' => $this->resource->student->name,
                'email' => $this->resource->student->email,
            ]),
            'teacher' => $this->whenLoaded('teacher', fn () => [
                'id' => $this->resource->teacher->id,
                'name' => $this->resource->teacher->name,
                'email' => $this->resource->teacher->email,
            ]),
        ];

        if ($canViewAdminFields) {
            $data += [
                'assigned_by' => $this->resource->assigned_by,
                'notes' => $this->resource->notes,
                'assigned_by_user' => $this->whenLoaded('assignedBy', fn () => $this->userSummary($this->resource->assignedBy, $request)),
                'created_at' => $this->resource->created_at,
                'updated_at' => $this->resource->updated_at,
            ];
        }

        return $data;
    }
}
