<?php

namespace App\Http\Resources\Subscriptions;

use App\Http\Resources\Concerns\SanitizesApiResponses;
use App\Models\Invoice;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionHistoryResource extends JsonResource
{
    use SanitizesApiResponses;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canViewAdminFields = $this->canViewAdminFields($request);
        $canViewBillingFields = $this->canViewBillingFields($request);

        return [
            'subscription_id' => $this->subscriptionPublicId(),
            'student_id' => $this->studentPublicId(),
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
            'previous_values' => $this->when($canViewAdminFields, fn () => $this->sanitizeValues($this->resource->previous_values ?? [])),
            'new_values' => $this->when($canViewAdminFields, fn () => $this->sanitizeValues($this->resource->new_values ?? [])),
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

    private function subscriptionPublicId(): ?string
    {
        if ($this->resource->relationLoaded('subscription')) {
            return $this->publicId($this->resource->subscription);
        }

        return Subscription::query()->whereKey($this->resource->subscription_id)->value('public_id');
    }

    private function studentPublicId(): ?string
    {
        if ($this->resource->relationLoaded('student')) {
            return $this->publicId($this->resource->student);
        }

        return $this->resource->student()->value('public_id');
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function sanitizeValues(array $values): array
    {
        if (array_key_exists('invoice_id', $values)) {
            $values['invoice_id'] = $this->invoicePublicId($values['invoice_id']);
        }

        if (array_key_exists('renewed_from_subscription_id', $values)) {
            $values['renewed_from_subscription_id'] = $this->subscriptionIdToPublicId($values['renewed_from_subscription_id']);
        }

        return $values;
    }

    private function invoicePublicId(mixed $id): ?string
    {
        if ($id === null || $id === '') {
            return null;
        }

        if (is_string($id) && ! is_numeric($id)) {
            return $id;
        }

        return Invoice::query()->whereKey($id)->value('public_id');
    }

    private function subscriptionIdToPublicId(mixed $id): ?string
    {
        if ($id === null || $id === '') {
            return null;
        }

        if (is_string($id) && ! is_numeric($id)) {
            return $id;
        }

        return Subscription::query()->whereKey($id)->value('public_id');
    }
}
