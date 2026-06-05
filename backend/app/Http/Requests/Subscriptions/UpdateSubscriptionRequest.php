<?php

namespace App\Http\Requests\Subscriptions;

use App\Http\Requests\Subscriptions\Concerns\ValidatesSubscriptionPayload;
use App\Models\Subscription;
use App\Models\User;
use App\Support\PublicIdResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates update subscription requests.
 *
 * Expected roles: Admin or staff users with the relevant subscription management permission.
 * Request-level authorize() documents any additional checks; otherwise route middleware, controller gates, and policies handle access.
 */
class UpdateSubscriptionRequest extends FormRequest
{
    use ValidatesSubscriptionPayload;

    /**
     * Resolve public student IDs to internal keys before subscription validation runs.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(PublicIdResolver::resolveFields($this->all(), [
            'student_id' => User::class,
        ]));
    }

    /**
     * Get validation rules for update subscription requests.
     *
     * Important rules: sometimes rules support partial updates or optional filters; enum rules constrain values to the relevant model constants; exists rules require referenced records to be present; public_id exists rules expect public identifiers instead of numeric primary keys.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'student_id' => ['sometimes', 'integer', 'exists:users,id'],
            'plan_name' => ['sometimes', 'string', 'max:255'],
            'package_type' => ['sometimes', 'string', Rule::in(Subscription::TYPES)],
            'total_lesson_count' => ['sometimes', 'integer', 'min:0'],
            'consumed_lesson_count' => ['sometimes', 'integer', 'min:0'],
            'remaining_lesson_count' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', 'string', Rule::in(Subscription::STATUSES)],
            'payment_status' => ['sometimes', 'string', Rule::in(Subscription::PAYMENT_STATUSES)],
            'invoice_id' => ['sometimes', 'nullable', 'string', 'exists:invoices,public_id'],
            'invoice_reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'internal_notes' => ['sometimes', 'nullable', 'string'],
            'renewal_reminder_due_at' => ['sometimes', 'nullable', 'date'],
            'renewal_reminder_last_sent_at' => ['sometimes', 'nullable', 'date'],
            'renewal_reminder_status' => ['sometimes', 'string', Rule::in(Subscription::RENEWAL_REMINDER_STATUSES)],
            'renewal_reminder_window_key' => ['sometimes', 'nullable', 'string', 'max:255'],
            'renewal_eligible' => ['sometimes', 'boolean'],
            'renewal_reminder_notes' => ['sometimes', 'nullable', 'string'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
