<?php

namespace App\Http\Resources\Payroll;

use App\Http\Resources\Concerns\SanitizesApiResponses;
use App\Models\PayoutPeriod;
use App\Models\TeacherPayoutAdjustment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TeacherPayoutAdjustment
 */
class TeacherPayoutAdjustmentResource extends JsonResource
{
    use SanitizesApiResponses;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isTeacher = $request->user()?->hasRole('teacher') ?? false;

        return [
            'teacher_id' => $this->publicIdFor(User::class, $this->resource->teacher_id),
            'teacher_name' => $this->when(! $isTeacher, $this->resource->teacher?->name),
            'payout_period_id' => $this->publicIdFor(PayoutPeriod::class, $this->resource->payout_period_id),
            'payout_period_name' => $this->whenLoaded('payoutPeriod', fn () => $this->resource->payoutPeriod?->name),
            'type' => $this->resource->type,
            'amount' => $this->resource->amount,
            'currency' => $this->resource->currency,
            'reason' => $this->resource->reason,
            'internal_notes' => $this->when(! $isTeacher, $this->resource->internal_notes),
            'created_by' => $this->when(! $isTeacher, fn () => $this->publicIdFor(User::class, $this->resource->created_by)),
            'created_by_name' => $this->when(! $isTeacher, $this->resource->createdBy?->name),
            'created_at' => $this->resource->created_at?->toISOString(),
        ];
    }
}
