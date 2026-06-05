<?php

namespace App\Http\Requests\Subscriptions;

use App\Models\Subscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates update subscription status requests.
 *
 * Expected roles: Admin or staff users with the relevant subscription management permission.
 * Request-level authorize() documents any additional checks; otherwise route middleware, controller gates, and policies handle access.
 */
class UpdateSubscriptionStatusRequest extends FormRequest
{
    /**
     * Get validation rules for update subscription status requests.
     *
     * Important rules: enum rules constrain values to the relevant model constants; partial update rules validate only supplied fields unless a supplied field is explicitly required.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(Subscription::STATUSES)],
        ];
    }
}
