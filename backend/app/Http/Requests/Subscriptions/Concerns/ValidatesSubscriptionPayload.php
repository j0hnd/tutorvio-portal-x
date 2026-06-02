<?php

namespace App\Http\Requests\Subscriptions\Concerns;

use App\Models\User;
use Illuminate\Validation\Validator;

/**
 * Provides validation behavior for subscription payload consistency validation.
 *
 * Expected roles: Admin or staff users with the relevant subscription management permission.
 * Request-level authorize() documents any additional checks; otherwise route middleware, controller gates, and policies handle access.
 */
trait ValidatesSubscriptionPayload
{
    /**
     * Register after-validation checks for subscription student role, lesson-count consistency, and date ranges.
     *
     * @param  mixed  $validator
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateStudent($validator);
            $this->validateLessonCounts($validator);
            $this->validateDateRange($validator);
        });
    }

    /**
     * Validate a conditional or role-scoped constraint for subscription payload consistency validation.
     *
     * @param  mixed  $validator
     */
    private function validateStudent(Validator $validator): void
    {
        if (! $this->filled('student_id')) {
            return;
        }

        $student = User::find($this->integer('student_id'));

        if (! $student?->hasRole('student')) {
            $validator->errors()->add('student_id', 'The selected user must be a student.');
        }
    }

    /**
     * Validate a conditional or role-scoped constraint for subscription payload consistency validation.
     *
     * @param  mixed  $validator
     */
    private function validateLessonCounts(Validator $validator): void
    {
        $subscription = $this->route('subscription');
        $total = $this->has('total_lesson_count')
            ? $this->integer('total_lesson_count')
            : (int) ($subscription?->total_lesson_count ?? 0);
        $consumed = $this->has('consumed_lesson_count')
            ? $this->integer('consumed_lesson_count')
            : (int) ($subscription?->consumed_lesson_count ?? 0);
        $remaining = $this->has('remaining_lesson_count')
            ? $this->integer('remaining_lesson_count')
            : (int) ($subscription?->remaining_lesson_count ?? 0);

        if ($consumed > $total) {
            $validator->errors()->add('consumed_lesson_count', 'Consumed lessons cannot exceed total lessons.');
        }

        if ($remaining > $total) {
            $validator->errors()->add('remaining_lesson_count', 'Remaining lessons cannot exceed total lessons.');
        }
    }

    /**
     * Validate a conditional or role-scoped constraint for subscription payload consistency validation.
     *
     * @param  mixed  $validator
     */
    private function validateDateRange(Validator $validator): void
    {
        $subscription = $this->route('subscription');
        $startsAt = $this->input('starts_at', $subscription?->starts_at);
        $endsAt = $this->input('ends_at', $subscription?->ends_at);

        if ($startsAt === null || $endsAt === null) {
            return;
        }

        if (strtotime((string) $endsAt) < strtotime((string) $startsAt)) {
            $validator->errors()->add('ends_at', 'End date cannot be earlier than start date.');
        }
    }
}
