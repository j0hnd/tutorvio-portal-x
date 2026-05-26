<?php

namespace App\Http\Resources\Subscriptions;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentPackageSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->resource->plan_name,
            'plan_name' => $this->resource->plan_name,
            'status' => $this->studentFacingStatus(),
            'starts_at' => $this->resource->starts_at,
            'ends_at' => $this->resource->ends_at,
            'total_lessons' => $this->resource->total_lesson_count,
            'consumed_lessons' => $this->resource->consumed_lesson_count,
            'remaining_lessons' => $this->resource->remaining_lesson_count,
            'payment_status' => $this->when($this->canViewBillingReferences($request), $this->resource->payment_status),
            'renewal_reminder' => $this->renewalReminder(),
            'invoice_reference' => $this->when(
                $this->canViewBillingReferences($request),
                $this->resource->invoice_reference ?? $this->resource->invoice?->invoice_number
            ),
        ];
    }

    private function studentFacingStatus(): string
    {
        if ($this->resource->is_frozen) {
            return 'frozen';
        }

        return $this->resource->status === Subscription::STATUS_ACTIVE
            ? Subscription::STATUS_ACTIVE
            : Subscription::STATUS_INACTIVE;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function renewalReminder(): ?array
    {
        if ($this->resource->ends_at === null) {
            return null;
        }

        $daysUntilEnd = now()->startOfDay()->diffInDays($this->resource->ends_at->copy()->startOfDay(), false);

        return [
            'reminder_date' => $this->resource->ends_at->copy()->subDays(7)->toDateString(),
            'days_until_end' => $daysUntilEnd,
            'should_renew_soon' => $daysUntilEnd >= 0 && $daysUntilEnd <= 7,
        ];
    }

    private function canViewBillingReferences(Request $request): bool
    {
        $user = $request->user();

        if ($user?->hasRole('student') === true) {
            return (bool) config('billing.invoice.student_visibility_enabled', true);
        }

        return $user?->hasRole('admin') === true
            || ($user?->hasRole('staff') === true && $user?->can('invoices.view') === true);
    }
}
