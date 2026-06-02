<?php

namespace App\Http\Requests\Subscriptions;

use App\Models\Subscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AdjustSubscriptionLessonBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
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
