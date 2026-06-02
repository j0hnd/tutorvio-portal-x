<?php

namespace App\Http\Requests\Subscriptions;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates update subscription notes requests.
 *
 * Expected roles: Admin or staff users with the relevant subscription management permission.
 * Request-level authorize() documents any additional checks; otherwise route middleware, controller gates, and policies handle access.
 */
class UpdateSubscriptionNotesRequest extends FormRequest
{
    /**
     * Get validation rules for update subscription notes requests.
     *
     * Important rules: conditional required rules accept aliases or require at least one meaningful content field; partial update rules validate only supplied fields unless a supplied field is explicitly required.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'internal_notes' => ['required_without:notes', 'nullable', 'string'],
            'notes' => ['required_without:internal_notes', 'nullable', 'string'],
        ];
    }
}
