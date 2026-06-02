<?php

namespace App\Http\Requests\AuditLogs;

use Illuminate\Foundation\Http\FormRequest;

class ListAuditLogsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'actor_user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'action_type' => ['sometimes', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'module' => ['sometimes', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'target_entity_type' => ['sometimes', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'target_entity_id' => ['sometimes', 'integer', 'min:1'],
            'date_from' => ['sometimes', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
