<?php

namespace App\Http\Requests\Subscriptions;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates update subscription invoice reference requests.
 *
 * Expected roles: Admin or staff users with the relevant subscription management permission.
 * Request-level authorize() documents any additional checks; otherwise route middleware, controller gates, and policies handle access.
 */
class UpdateSubscriptionInvoiceReferenceRequest extends FormRequest
{
    /**
     * Get validation rules for update subscription invoice reference requests.
     *
     * Important rules: conditional required rules accept aliases or require at least one meaningful content field; sometimes rules support partial updates or optional filters; exists rules require referenced records to be present; public_id exists rules expect public identifiers instead of numeric primary keys.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'invoice_reference' => ['required_without:reference', 'nullable', 'string', 'max:255'],
            'reference' => ['required_without:invoice_reference', 'nullable', 'string', 'max:255'],
            'invoice_id' => ['sometimes', 'nullable', 'string', 'exists:invoices,public_id'],
        ];
    }
}
