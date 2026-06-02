<?php

namespace App\Http\Resources\Scheduling;

use App\Http\Resources\Concerns\SanitizesApiResponses;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherUnavailableDateResource extends JsonResource
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
            'starts_at' => $this->resource->starts_at,
            'ends_at' => $this->resource->ends_at,
            'timezone' => $this->resource->timezone,
            'is_all_day' => $this->resource->is_all_day,
            'reason' => $this->resource->reason,
            'teacher' => $this->whenLoaded('teacher', fn () => $this->userSummary($this->resource->teacher, $request)),
        ];
    }
}
