<?php

namespace App\Http\Resources\CourseCatalog;

use App\Http\Resources\Concerns\SanitizesApiResponses;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseProgramStudentAssignmentResource extends JsonResource
{
    use SanitizesApiResponses;

    /**
     * Transform a course-program student assignment response.
     *
     * Public-safe fields expose the assigned course program, student summary,
     * assigned date, status, and start date using public IDs. Assignment owner,
     * notes, and timestamps are admin-only or require course_programs.view.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canViewAdminFields = $this->canViewAdminFields($request, 'course_programs.view');

        $data = [
            'course_program_id' => $this->whenLoaded('courseProgram', fn () => $this->publicId($this->resource->courseProgram)),
            'course_program' => $this->whenLoaded('courseProgram', fn () => new CourseProgramResource($this->resource->courseProgram)),
            'student_id' => $this->whenLoaded('student', fn () => $this->publicId($this->resource->student)),
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
