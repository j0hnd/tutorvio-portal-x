<?php

namespace App\Http\Resources\CourseCatalog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseProgramStudentAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'course_program_id' => $this->resource->course_program_id,
            'course_program' => $this->whenLoaded('courseProgram', fn () => new CourseProgramResource($this->resource->courseProgram)),
            'student_id' => $this->resource->student_id,
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->resource->student->id,
                'name' => $this->resource->student->name,
                'email' => $this->resource->student->email,
                'status' => $this->resource->student->status,
            ]),
            'assigned_by' => $this->resource->assigned_by,
            'assigned_by_user' => $this->whenLoaded('assignedBy', fn () => $this->resource->assignedBy === null ? null : [
                'id' => $this->resource->assignedBy->id,
                'name' => $this->resource->assignedBy->name,
                'email' => $this->resource->assignedBy->email,
            ]),
            'assigned_at' => $this->resource->assigned_at,
            'status' => $this->resource->status,
            'start_date' => $this->resource->start_date,
            'notes' => $this->resource->notes,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
