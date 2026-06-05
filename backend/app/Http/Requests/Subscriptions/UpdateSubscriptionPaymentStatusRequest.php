<?php

namespace App\Http\Requests\Subscriptions;

use App\Models\Subscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates update subscription payment status requests.
 *
 * Expected roles: Admin or staff users with the relevant subscription management permission.
 * Request-level authorize() documents any additional checks; otherwise route middleware, controller gates, and policies handle access.
 */
class UpdateSubscriptionPaymentStatusRequest extends FormRequest
{
    /**
     * Get validation rules for update subscription payment status requests.
     *
     * Important rules: conditional required rules accept aliases or require at least one meaningful content field; enum rules constrain values to the relevant model constants; partial update rules validate only supplied fields unless a supplied field is explicitly required.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payment_status' => ['required_without:status', 'string', Rule::in(Subscription::PAYMENT_STATUSES)],
            'status' => ['required_without:payment_status', 'string', Rule::in(Subscription::PAYMENT_STATUSES)],
        ];
    }
}
