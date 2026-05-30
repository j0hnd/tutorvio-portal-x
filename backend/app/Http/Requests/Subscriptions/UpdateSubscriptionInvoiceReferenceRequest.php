<?php

namespace App\Http\Requests\Subscriptions;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubscriptionInvoiceReferenceRequest extends FormRequest
{
    /**
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
