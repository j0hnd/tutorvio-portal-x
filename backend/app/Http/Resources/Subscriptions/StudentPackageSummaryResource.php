<?php

namespace App\Http\Resources\Subscriptions;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentPackageSummaryResource extends JsonResource
{
    /**
     * Transform a subscription into a student-facing package summary.
     *
     * Public fields expose package name, lesson counts, dates, renewal reminder,
     * and student-facing status. Payment status and invoice reference are visible
     * only to admins, permitted staff, or the owning student. Database IDs are not
     * exposed in this summary.
     *
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
        if (
            $this->resource->ends_at === null
            && $this->resource->renewal_reminder_due_at === null
            && $this->resource->renewal_reminder_status === Subscription::RENEWAL_REMINDER_STATUS_NONE
        ) {
            return null;
        }

        $daysUntilEnd = $this->resource->ends_at === null
            ? null
            : now()->startOfDay()->diffInDays($this->resource->ends_at->copy()->startOfDay(), false);

        return [
            'reminder_date' => $this->resource->renewal_reminder_due_at?->toDateString()
                ?? $this->resource->ends_at?->copy()->subDays(7)->toDateString(),
            'due_at' => $this->resource->renewal_reminder_due_at,
            'last_sent_at' => $this->resource->renewal_reminder_last_sent_at,
            'status' => $this->resource->renewal_reminder_status,
            'renewal_eligible' => $this->resource->renewal_eligible,
            'days_until_end' => $daysUntilEnd,
            'should_renew_soon' => $this->resource->renewal_reminder_status === Subscription::RENEWAL_REMINDER_STATUS_PENDING
                || ($daysUntilEnd !== null && $daysUntilEnd >= 0 && $daysUntilEnd <= 7),
        ];
    }

    /**
     * Determine whether package-summary billing references can be serialized.
     *
     * Students can view billing references only when
     * `billing.invoice.student_visibility_enabled` is enabled. Admins can view
     * them. Staff need `invoices.view`. Teachers and staff without permission
     * are denied.
     */
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
