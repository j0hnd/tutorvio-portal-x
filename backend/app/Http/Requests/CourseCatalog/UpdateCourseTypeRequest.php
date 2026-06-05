<?php

namespace App\Http\Requests\CourseCatalog;

use App\Models\CourseType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates update course type requests.
 *
 * Expected roles: Authenticated catalog managers, typically admin or staff users allowed by controller authorization.
 * Request-level authorize() documents any additional checks; otherwise route middleware, controller gates, and policies handle access.
 */
class UpdateCourseTypeRequest extends FormRequest
{
    /**
     * Determine whether the authenticated user can submit update course type requests. This request adds no request-local authorization beyond route middleware, controller gates, or policies.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get validation rules for update course type requests.
     *
     * Important rules: sometimes rules support partial updates or optional filters; catalog uniqueness and existence checks ignore archived records; partial update rules validate only supplied fields unless a supplied field is explicitly required.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $courseType = $this->route('courseType');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('course_types', 'name')
                    ->where(fn ($query) => $query->where('is_archived', false))
                    ->ignore($courseType instanceof CourseType ? $courseType->id : null),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
