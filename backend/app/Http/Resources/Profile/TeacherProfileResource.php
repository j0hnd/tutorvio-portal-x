<?php

namespace App\Http\Resources\Profile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isAdminOrStaff = $user->hasRole(['admin', 'staff']);

        $data = [
            'id' => $this->resource->id,
            'specialization' => $this->resource->specialization,
            'bio' => $this->resource->bio,
            'expertise' => $this->resource->expertise,
            'class_load' => $this->resource->class_load,
            'teaching_availability' => $this->resource->teaching_availability,
            'performance_summary' => $this->resource->performance_summary,
        ];

        if ($isAdminOrStaff) {
            $data['internal_status'] = $this->resource->internal_status;
            $data['teaching_notes'] = $this->resource->teaching_notes;
            $data['internal_remarks'] = $this->resource->internal_remarks;
            $data['document_contract_status'] = $this->resource->document_contract_status;
        }

        if ($this->relationLoaded('assignedStudents')) {
            $data['assigned_students'] = StudentProfileResource::collection($this->resource->assignedStudents);
        }

        return $data;
    }
}
