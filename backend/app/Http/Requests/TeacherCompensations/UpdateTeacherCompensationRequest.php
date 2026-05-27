<?php

namespace App\Http\Requests\TeacherCompensations;

use App\Http\Requests\TeacherCompensations\Concerns\ValidatesTeacherCompensationPayload;
use App\Models\TeacherCompensation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeacherCompensationRequest extends FormRequest
{
    use ValidatesTeacherCompensationPayload;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'teacher_id' => ['sometimes', 'integer', 'exists:users,id'],
            'pay_model' => ['sometimes', 'string', Rule::in(TeacherCompensation::PAY_MODELS)],
            'default_pay_rate' => ['sometimes', 'numeric', 'min:0'],
            'base_rate' => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'effective_start_date' => ['sometimes', 'date'],
            'effective_end_date' => ['sometimes', 'nullable', 'date', 'after:effective_start_date'],
            'internal_admin_notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
