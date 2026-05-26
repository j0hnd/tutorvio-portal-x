<?php

namespace App\Http\Requests\Subscriptions;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubscriptionNotesRequest extends FormRequest
{
    /**
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
