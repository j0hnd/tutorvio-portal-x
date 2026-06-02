<?php

namespace App\Http\Requests\Homeworks;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates review homework requests.
 *
 * Expected roles: Authenticated students, teachers, staff, or admins allowed by homework routes, controller checks, or policies.
 * Request-level authorize() documents any additional checks; otherwise route middleware, controller gates, and policies handle access.
 */
class ReviewHomeworkRequest extends FormRequest
{
    /**
     * Get validation rules for review homework requests.
     *
     * Important rules: sometimes rules support partial updates or optional filters.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'teacher_feedback' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
