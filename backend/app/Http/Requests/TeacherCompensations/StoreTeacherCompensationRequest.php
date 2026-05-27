<?php

namespace App\Http\Requests\TeacherCompensations;

use App\Http\Requests\TeacherCompensations\Concerns\ValidatesTeacherCompensationPayload;
use App\Models\TeacherCompensation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeacherCompensationRequest extends FormRequest
{
    use ValidatesTeacherCompensationPayload;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'teacher_id' => ['required', 'integer', 'exists:users,id'],
            'pay_model' => ['required', 'string', Rule::in(TeacherCompensation::PAY_MODELS)],
            'default_pay_rate' => ['required', 'numeric', 'min:0'],
            'base_rate' => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'effective_start_date' => ['required', 'date'],
            'effective_end_date' => ['sometimes', 'nullable', 'date', 'after:effective_start_date'],
            'internal_admin_notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
