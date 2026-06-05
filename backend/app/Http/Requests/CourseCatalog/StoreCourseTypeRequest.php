<?php

namespace App\Http\Requests\CourseCatalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates create course type requests.
 *
 * Expected roles: Authenticated catalog managers, typically admin or staff users allowed by controller authorization.
 * Request-level authorize() documents any additional checks; otherwise route middleware, controller gates, and policies handle access.
 */
class StoreCourseTypeRequest extends FormRequest
{
    /**
     * Determine whether the authenticated user can submit create course type requests. This request adds no request-local authorization beyond route middleware, controller gates, or policies.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get validation rules for create course type requests.
     *
     * Important rules: sometimes rules support partial updates or optional filters; catalog uniqueness and existence checks ignore archived records; required rules define the minimum payload for creation.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('course_types', 'name')->where(fn ($query) => $query->where('is_archived', false)),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
