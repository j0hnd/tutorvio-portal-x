<?php

namespace App\Http\Requests\Subscriptions;

use App\Http\Requests\Subscriptions\Concerns\ValidatesSubscriptionPayload;
use App\Models\Subscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubscriptionRequest extends FormRequest
{
    use ValidatesSubscriptionPayload;

    /**
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
