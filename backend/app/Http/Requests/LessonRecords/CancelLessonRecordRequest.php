<?php

namespace App\Http\Requests\LessonRecords;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates cancel lesson record requests.
 *
 * Expected roles: Authenticated teachers, staff, or admins allowed by lesson record routes, controller checks, or policies.
 * Request-level authorize() documents any additional checks; otherwise route middleware, controller gates, and policies handle access.
 */
class CancelLessonRecordRequest extends FormRequest
{
    /**
     * Determine whether the authenticated user can submit cancel lesson record requests. This request adds no request-local authorization beyond route middleware, controller gates, or policies.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get validation rules for cancel lesson record requests.
     *
     * Important rules: sometimes rules support partial updates or optional filters.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
