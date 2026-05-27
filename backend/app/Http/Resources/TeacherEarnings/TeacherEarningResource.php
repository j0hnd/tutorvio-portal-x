<?php

namespace App\Http\Resources\TeacherEarnings;

use App\Models\TeacherEarning;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TeacherEarning
 */
class TeacherEarningResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $metadata = is_array($this->resource->calculation_metadata)
            ? $this->resource->calculation_metadata
            : [];

        return [
            'id' => $this->resource->id,
            'teacher_id' => $this->resource->teacher_id,
            'teacher_name' => $this->resource->teacher?->name,
            'earning_source' => [
                'type' => $this->resource->source_type,
                'id' => $this->resource->source_id,
            ],
            'lesson_reference' => $this->whenLoaded('lessonRecord', fn () => $this->resource->lessonRecord ? [
                'id' => $this->resource->lessonRecord->id,
                'scheduled_date' => $this->resource->lessonRecord->scheduled_date?->toDateString(),
                'lesson_type' => $this->resource->lessonRecord->lesson_type,
                'lesson_status' => $this->resource->lessonRecord->lesson_status,
            ] : null),
            'course_reference' => isset($metadata['course_program_id']) ? [
                'id' => $metadata['course_program_id'],
                'course_type_id' => $metadata['course_type_id'] ?? null,
            ] : null,
            'pay_model' => $this->resource->pay_model,
            'rate_used' => $this->resource->rate_used,
            'quantity' => $this->resource->quantity,
            'amount' => $this->resource->amount,
            'currency' => $this->resource->currency,
            'status' => $this->resource->status,
            'earning_date' => $this->earningDate($metadata),
            'payout_period' => $metadata['payout_period'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function earningDate(array $metadata): ?string
    {
        if (is_string($metadata['scheduled_date'] ?? null)) {
            return $metadata['scheduled_date'];
        }

        if ($this->resource->relationLoaded('lessonRecord') && $this->resource->lessonRecord?->scheduled_date) {
            return $this->resource->lessonRecord->scheduled_date->toDateString();
        }

        return $this->resource->created_at?->toDateString();
    }
}
