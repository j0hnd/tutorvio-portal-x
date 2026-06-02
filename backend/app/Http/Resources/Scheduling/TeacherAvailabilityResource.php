<?php

namespace App\Http\Resources\Scheduling;

use App\Http\Resources\Concerns\SanitizesApiResponses;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherAvailabilityResource extends JsonResource
{
    use SanitizesApiResponses;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->publicId($this->resource),
            'teacher_id' => $this->whenLoaded('teacher', fn () => $this->publicId($this->resource->teacher)),
            'day_of_week' => $this->resource->day_of_week,
            'start_time' => $this->resource->start_time,
            'end_time' => $this->resource->end_time,
            'timezone' => $this->resource->timezone,
            'effective_from' => $this->resource->effective_from?->toDateString(),
            'effective_until' => $this->resource->effective_until?->toDateString(),
            'capacity' => $this->resource->capacity,
            'is_active' => $this->resource->is_active,
            'notes' => $this->resource->notes,
            'teacher' => $this->whenLoaded('teacher', fn () => $this->userSummary($this->resource->teacher, $request)),
        ];
    }
}
