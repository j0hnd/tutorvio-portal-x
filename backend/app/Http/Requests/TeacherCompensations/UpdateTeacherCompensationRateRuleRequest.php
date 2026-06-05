<?php

namespace App\Http\Requests\TeacherCompensations;

use App\Models\CourseProgram;
use App\Models\CourseType;
use App\Models\TeacherCompensation;
use App\Support\PublicIdResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates update teacher compensation rate rule requests.
 *
 * Expected roles: Admin or staff users with teacher compensation management access.
 * Request-level authorize() documents any additional checks; otherwise route middleware, controller gates, and policies handle access.
 */
class UpdateTeacherCompensationRateRuleRequest extends FormRequest
{
    /**
     * Resolve public course references to internal keys for rate matching.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(PublicIdResolver::resolveFields($this->all(), [
            'course_type_id' => CourseType::class,
            'course_program_id' => CourseProgram::class,
        ]));
    }

    /**
     * Determine whether the authenticated user can submit update teacher compensation rate rule requests. This request adds no request-local authorization beyond route middleware, controller gates, or policies.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get validation rules for update teacher compensation rate rule requests.
     *
     * Important rules: sometimes rules support partial updates or optional filters; enum rules constrain values to the relevant model constants; exists rules require referenced records to be present; partial update rules validate only supplied fields unless a supplied field is explicitly required.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lesson_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'experience_level' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contract_agreement' => ['sometimes', 'nullable', 'string', 'max:255'],
            'course_type_id' => ['sometimes', 'nullable', 'integer', 'exists:course_types,id'],
            'course_program_id' => ['sometimes', 'nullable', 'integer', 'exists:course_programs,id'],
            'pay_model' => ['sometimes', 'nullable', 'string', Rule::in(TeacherCompensation::PAY_MODELS)],
            'pay_rate' => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
            'internal_admin_notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
