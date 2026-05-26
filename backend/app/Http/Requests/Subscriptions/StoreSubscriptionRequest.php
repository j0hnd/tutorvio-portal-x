<?php

namespace App\Http\Requests\Subscriptions;

use App\Http\Requests\Subscriptions\Concerns\ValidatesSubscriptionPayload;
use App\Models\Subscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubscriptionRequest extends FormRequest
{
    use ValidatesSubscriptionPayload;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:users,id'],
            'plan_name' => ['required', 'string', 'max:255'],
            'package_type' => ['required', 'string', Rule::in(Subscription::TYPES)],
            'total_lesson_count' => ['required', 'integer', 'min:0'],
            'consumed_lesson_count' => ['required', 'integer', 'min:0'],
            'remaining_lesson_count' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'string', Rule::in(Subscription::STATUSES)],
            'payment_status' => ['required', 'string', Rule::in(Subscription::PAYMENT_STATUSES)],
            'invoice_id' => ['sometimes', 'nullable', 'integer', 'exists:invoices,id'],
            'invoice_reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'internal_notes' => ['sometimes', 'nullable', 'string'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
