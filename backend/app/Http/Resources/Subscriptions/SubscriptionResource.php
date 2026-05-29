<?php

namespace App\Http\Resources\Subscriptions;

use App\Http\Resources\Concerns\SanitizesApiResponses;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    use SanitizesApiResponses;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->publicId($this->resource),
            'student_id' => $this->whenLoaded('student', fn () => $this->publicId($this->resource->student)),
            'plan_name' => $this->resource->plan_name,
            'package_type' => $this->resource->package_type,
            'total_lesson_count' => $this->resource->total_lesson_count,
            'consumed_lesson_count' => $this->resource->consumed_lesson_count,
            'remaining_lesson_count' => $this->resource->remaining_lesson_count,
            'status' => $this->resource->status,
            'is_frozen' => $this->resource->is_frozen,
            'frozen_at' => $this->resource->frozen_at,
            'payment_status' => $this->resource->payment_status,
            'invoice_id' => $this->invoicePublicId(),
            'invoice_reference' => $this->resource->invoice_reference,
            'renewed_from_subscription_id' => $this->renewedFromPublicId(),
            'renewal_reminder_due_at' => $this->resource->renewal_reminder_due_at,
            'renewal_reminder_last_sent_at' => $this->resource->renewal_reminder_last_sent_at,
            'renewal_reminder_status' => $this->resource->renewal_reminder_status,
            'renewal_reminder_window_key' => $this->when($this->canViewAdminFields($request), $this->resource->renewal_reminder_window_key),
            'renewal_eligible' => $this->resource->renewal_eligible,
            'renewal_reminder_notes' => $this->when($this->canViewAdminFields($request), $this->resource->renewal_reminder_notes),
            'starts_at' => $this->resource->starts_at,
            'ends_at' => $this->resource->ends_at,
            'internal_notes' => $this->when($this->canViewAdminFields($request), $this->resource->internal_notes),
            'created_by' => $this->when($this->canViewAdminFields($request), $this->resource->created_by),
            'updated_by' => $this->when($this->canViewAdminFields($request), $this->resource->updated_by),
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->publicId($this->resource->student),
                'name' => $this->resource->student->name,
                'email' => $this->resource->student->email,
                'status' => $this->resource->student->status,
            ]),
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }

    private function canViewAdminFields(Request $request): bool
    {
        $user = $request->user();

        return $user?->hasRole('admin') === true
            || ($user?->hasRole('staff') === true && $user?->can('subscriptions.view') === true);
    }

    private function invoicePublicId(): ?string
    {
        if ($this->resource->invoice_id === null) {
            return null;
        }

        if ($this->resource->relationLoaded('invoice')) {
            return $this->publicId($this->resource->invoice);
        }

        return $this->resource->invoice()->value('public_id');
    }

    private function renewedFromPublicId(): ?string
    {
        if ($this->resource->renewed_from_subscription_id === null) {
            return null;
        }

        if ($this->resource->relationLoaded('renewedFrom')) {
            return $this->publicId($this->resource->renewedFrom);
        }

        return $this->resource->renewedFrom()->value('public_id');
    }
}
