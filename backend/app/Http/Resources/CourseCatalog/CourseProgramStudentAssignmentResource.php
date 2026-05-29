<?php

namespace App\Http\Resources\CourseCatalog;

use App\Http\Resources\Concerns\SanitizesApiResponses;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseProgramStudentAssignmentResource extends JsonResource
{
    use SanitizesApiResponses;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canViewAdminFields = $this->canViewAdminFields($request, 'course_programs.view');

        $data = [
            'id' => $this->resource->id,
            'course_program_id' => $this->resource->course_program_id,
            'course_program' => $this->whenLoaded('courseProgram', fn () => new CourseProgramResource($this->resource->courseProgram)),
            'student_id' => $this->resource->student_id,
            'student' => $this->whenLoaded('student', fn () => $this->userSummary($this->resource->student, $request)),
            'assigned_at' => $this->resource->assigned_at,
            'status' => $this->resource->status,
            'start_date' => $this->resource->start_date,
        ];

        if ($canViewAdminFields) {
            $data += [
                'assigned_by' => $this->resource->assigned_by,
                'assigned_by_user' => $this->whenLoaded('assignedBy', fn () => $this->userSummary($this->resource->assignedBy, $request)),
                'notes' => $this->resource->notes,
                'created_at' => $this->resource->created_at,
                'updated_at' => $this->resource->updated_at,
            ];
        }

        return $data;
    }
}
