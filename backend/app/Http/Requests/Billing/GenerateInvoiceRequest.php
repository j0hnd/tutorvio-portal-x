<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;

class GenerateInvoiceRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:users,id'],
            'subscription_id' => ['required_without:course_program_id', 'integer', 'exists:subscriptions,id'],
            'course_program_id' => ['required_without:subscription_id', 'integer', 'exists:course_programs,id'],
            'subtotal' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'issued_date' => ['sometimes', 'date'],
            'due_date' => ['sometimes', 'date', 'after_or_equal:issued_date'],
            'allow_duplicate' => ['sometimes', 'boolean'],
            'payment_reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'metadata' => ['sometimes', 'array'],
        ];
    }
}
