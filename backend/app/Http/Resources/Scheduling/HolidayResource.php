<?php

namespace App\Http\Resources\Scheduling;

use App\Http\Resources\Concerns\SanitizesApiResponses;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HolidayResource extends JsonResource
{
    use SanitizesApiResponses;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->publicId($this->resource),
            'name' => $this->resource->name,
            'date' => $this->resource->date?->toDateString(),
            'timezone' => $this->resource->timezone,
            'country_code' => $this->resource->country_code,
            'repeats_annually' => $this->resource->repeats_annually,
            'is_active' => $this->resource->is_active,
            'notes' => $this->resource->notes,
        ];
    }
}
