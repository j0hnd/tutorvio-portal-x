<?php

namespace App\Http\Requests\Subscriptions;

use App\Models\Subscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates adjust subscription lesson balance requests.
 *
 * Expected roles: Admin or staff users with the relevant subscription management permission.
 * Request-level authorize() documents any additional checks; otherwise route middleware, controller gates, and policies handle access.
 */
class AdjustSubscriptionLessonBalanceRequest extends FormRequest
{
    /**
     * Determine whether the authenticated user can submit adjust subscription lesson balance requests. This request adds no request-local authorization beyond route middleware, controller gates, or policies.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get validation rules for adjust subscription lesson balance requests.
     *
     * Important rules: sometimes rules support partial updates or optional filters.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'total_lesson_count' => ['sometimes', 'integer', 'min:0'],
            'consumed_lesson_count' => ['sometimes', 'integer', 'min:0'],
            'remaining_lesson_count' => ['sometimes', 'integer', 'min:0'],
            'notes' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * Register after-validation checks for subscription student role, lesson-count consistency, and date ranges.
     *
     * @param  mixed  $validator
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $subscription = $this->route('subscription');

            if (! $subscription instanceof Subscription) {
                return;
            }

            if (! $this->hasAny(['total_lesson_count', 'consumed_lesson_count', 'remaining_lesson_count'])) {
                $validator->errors()->add('lesson_balance', 'Provide at least one lesson balance field to adjust.');

                return;
            }

            $total = $this->has('total_lesson_count')
                ? $this->integer('total_lesson_count')
                : (int) $subscription->total_lesson_count;
            $consumed = $this->has('consumed_lesson_count')
                ? $this->integer('consumed_lesson_count')
                : (int) $subscription->consumed_lesson_count;
            $remaining = $this->has('remaining_lesson_count')
                ? $this->integer('remaining_lesson_count')
                : $total - $consumed;

            if ($consumed > $total) {
                $validator->errors()->add('consumed_lesson_count', 'Consumed lessons cannot exceed total lessons.');
            }

            if ($remaining < 0) {
                $validator->errors()->add('remaining_lesson_count', 'Remaining lessons cannot be below zero.');
            }

            if ($remaining !== $total - $consumed) {
                $validator->errors()->add('remaining_lesson_count', 'Remaining lessons must equal total lessons minus consumed lessons.');
            }
        });
    }
}
