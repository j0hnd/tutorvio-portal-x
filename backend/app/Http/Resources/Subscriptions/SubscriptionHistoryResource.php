<?php

namespace App\Http\Resources\Subscriptions;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canViewAdminFields = $this->canViewAdminFields($request);
        $canViewBillingFields = $this->canViewBillingFields($request);

        return [
            'id' => $this->resource->id,
            'subscription_id' => $this->resource->subscription_id,
            'student_id' => $this->resource->student_id,
            'event_type' => $this->resource->event_type,
            'plan_name' => $this->resource->plan_name,
            'package_type' => $this->resource->package_type,
            'total_lesson_count' => $this->resource->total_lesson_count,
            'consumed_lesson_count' => $this->resource->consumed_lesson_count,
            'remaining_lesson_count' => $this->resource->remaining_lesson_count,
            'status' => $this->resource->status,
            'is_frozen' => $this->resource->is_frozen,
            'payment_status' => $this->when($canViewBillingFields, $this->resource->payment_status),
            'starts_at' => $this->resource->starts_at,
            'ends_at' => $this->resource->ends_at,
            'previous_values' => $this->when($canViewAdminFields, $this->resource->previous_values),
            'new_values' => $this->when($canViewAdminFields, $this->resource->new_values),
            'notes' => $this->when($canViewAdminFields, $this->resource->notes),
            'effective_at' => $this->resource->effective_at,
            'created_by' => $this->when($canViewAdminFields, $this->resource->created_by),
            'created_at' => $this->resource->created_at,
        ];
    }

    private function canViewAdminFields(Request $request): bool
    {
        $user = $request->user();

        return $user?->hasRole('admin') === true
            || ($user?->hasRole('staff') === true && $user?->can('subscriptions.view') === true);
    }

    private function canViewBillingFields(Request $request): bool
    {
        return $this->canViewAdminFields($request);
    }
}
