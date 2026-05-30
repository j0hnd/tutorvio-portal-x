<?php

namespace App\Http\Resources\TeacherCompensations;

use App\Http\Resources\Concerns\SanitizesApiResponses;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherCompensationRateRuleResource extends JsonResource
{
    use SanitizesApiResponses;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->publicId($this->resource),
            'teacher_compensation_id' => $this->whenLoaded('teacherCompensation', fn () => $this->publicId($this->resource->teacherCompensation)),
            'lesson_type' => $this->resource->lesson_type,
            'lesson_type_override_rate' => $this->when($this->resource->lesson_type !== null, $this->resource->pay_rate),
            'experience_level' => $this->resource->experience_level,
            'experience_level_override_rate' => $this->when($this->resource->experience_level !== null, $this->resource->pay_rate),
            'contract_agreement' => $this->resource->contract_agreement,
            'contract_agreement_override_rate' => $this->when($this->resource->contract_agreement !== null, $this->resource->pay_rate),
            'course_type_id' => $this->resource->course_type_id,
            'course_program_id' => $this->resource->course_program_id,
            'course_override_rate' => $this->when($this->resource->course_type_id !== null || $this->resource->course_program_id !== null, $this->resource->pay_rate),
            'pay_model' => $this->resource->pay_model,
            'pay_rate' => $this->resource->pay_rate,
            'currency' => $this->resource->currency,
            'priority' => $this->resource->priority,
            'is_active' => $this->resource->is_active,
            'internal_admin_notes' => $this->resource->internal_admin_notes,
            'course_type' => $this->whenLoaded('courseType', fn () => [
                'id' => $this->resource->courseType->id,
                'name' => $this->resource->courseType->name,
                'slug' => $this->resource->courseType->slug,
            ]),
            'course_program' => $this->whenLoaded('courseProgram', fn () => [
                'id' => $this->resource->courseProgram->id,
                'name' => $this->resource->courseProgram->name,
                'slug' => $this->resource->courseProgram->slug,
            ]),
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
