<?php

namespace App\Http\Resources\Profile;

use App\Http\Resources\Concerns\SanitizesApiResponses;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherProfileResource extends JsonResource
{
    use SanitizesApiResponses;

    public function toArray(Request $request): array
    {
        $isAdminOrStaff = $this->canViewAdminFields($request);

        $data = [
            'id' => $this->teacherPublicId(),
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

        if ($isAdminOrStaff && $this->relationLoaded('assignedStudents')) {
            $data['assigned_students'] = StudentProfileResource::collection($this->resource->assignedStudents);
        }

        return $data;
    }

    private function teacherPublicId(): ?string
    {
        if ($this->relationLoaded('user')) {
            return $this->publicId($this->resource->user);
        }

        return User::query()
            ->whereKey($this->resource->user_id)
            ->value('public_id');
    }
}
