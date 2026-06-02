<?php

namespace App\Http\Resources\PayoutPeriods;

use App\Http\Resources\Concerns\SanitizesApiResponses;
use App\Http\Resources\TeacherEarnings\TeacherEarningResource;
use App\Models\PayoutPeriod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PayoutPeriod
 */
class PayoutPeriodResource extends JsonResource
{
    use SanitizesApiResponses;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->publicId($this->resource),
            'name' => $this->resource->name,
            'start_date' => $this->resource->start_date?->toDateString(),
            'end_date' => $this->resource->end_date?->toDateString(),
            'cutoff_date' => $this->resource->cutoff_date?->toDateString(),
            'payout_date' => $this->resource->payout_date?->toDateString(),
            'status' => $this->resource->status,
            'notes' => $this->resource->notes,
            'earnings_count' => $this->resource->earnings_count ?? $this->whenLoaded('earnings', fn () => $this->resource->earnings->count()),
            'earnings_total' => $this->resource->earnings_sum_amount ?? null,
            'earnings' => $this->whenLoaded('earnings', fn () => TeacherEarningResource::collection($this->resource->earnings)),
            'created_by' => $this->resource->created_by,
            'updated_by' => $this->resource->updated_by,
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
