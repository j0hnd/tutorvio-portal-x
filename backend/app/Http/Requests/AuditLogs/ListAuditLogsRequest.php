<?php

namespace App\Http\Requests\AuditLogs;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates list audit logs requests.
 *
 * Expected roles: Admin or staff users with audit log view access.
 * Request-level authorize() documents any additional checks; otherwise route middleware, controller gates, and policies handle access.
 */
class ListAuditLogsRequest extends FormRequest
{
    /**
     * Get validation rules for list audit logs requests.
     *
     * Important rules: sometimes rules support partial updates or optional filters; exists rules require referenced records to be present; date and time ordering rules keep ranges consistent; regex rules restrict filter tokens to safe identifier characters.
     *
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
