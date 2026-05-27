<?php

namespace App\Http\Resources\TeacherCompensations;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherCompensationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'teacher_id' => $this->resource->teacher_id,
            'pay_model' => $this->resource->pay_model,
            'base_rate' => $this->resource->default_pay_rate,
            'default_pay_rate' => $this->resource->default_pay_rate,
            'currency' => $this->resource->currency,
            'effective_start_date' => $this->resource->effective_start_date,
            'effective_end_date' => $this->resource->effective_end_date,
            'internal_admin_notes' => $this->resource->internal_admin_notes,
            'archived_at' => $this->resource->archived_at,
            'archived_by' => $this->resource->archived_by,
            'teacher' => $this->whenLoaded('teacher', fn () => [
                'id' => $this->resource->teacher->id,
                'name' => $this->resource->teacher->name,
                'email' => $this->resource->teacher->email,
                'status' => $this->resource->teacher->status,
            ]),
            'rate_rules' => $this->whenLoaded(
                'rateRules',
                fn () => TeacherCompensationRateRuleResource::collection($this->resource->rateRules)
            ),
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}
