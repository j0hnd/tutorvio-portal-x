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
            'id' => $this->publicId($this->resource),
            'student_id' => $this->whenLoaded('student', fn () => $this->publicId($this->resource->student)),
            'teacher_id' => $this->whenLoaded('teacher', fn () => $this->publicId($this->resource->teacher)),
            'assigned_at' => $this->resource->assigned_at,
            'status' => $this->resource->status,
            'reason' => $this->resource->reason,
            'ended_at' => $this->resource->ended_at,
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->publicId($this->resource->student),
                'name' => $this->resource->student->name,
                'email' => $this->resource->student->email,
            ]),
            'teacher' => $this->whenLoaded('teacher', fn () => [
                'id' => $this->publicId($this->resource->teacher),
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
