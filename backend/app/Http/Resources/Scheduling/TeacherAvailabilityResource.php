<?php

namespace App\Http\Resources\Scheduling;

use App\Http\Resources\Concerns\SanitizesApiResponses;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherAvailabilityResource extends JsonResource
{
    use SanitizesApiResponses;

    /**
     * Transform teacher availability into a public-safe scheduling response.
     *
     * Public fields expose availability windows, timezone, recurrence details, and
     * loaded teacher summary using public IDs. Database primary keys and private
     * teacher internals should not be exposed.
     *
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
