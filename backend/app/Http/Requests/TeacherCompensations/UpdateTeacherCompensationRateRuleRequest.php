<?php

namespace App\Http\Requests\TeacherCompensations;

use App\Models\TeacherCompensation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeacherCompensationRateRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
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
