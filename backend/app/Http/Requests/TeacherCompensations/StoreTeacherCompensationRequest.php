<?php

namespace App\Http\Requests\TeacherCompensations;

use App\Http\Requests\TeacherCompensations\Concerns\ValidatesTeacherCompensationPayload;
use App\Models\TeacherCompensation;
use App\Models\User;
use App\Support\PublicIdResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates create teacher compensation requests.
 *
 * Expected roles: Admin or staff users with teacher compensation management access.
 * Request-level authorize() documents any additional checks; otherwise route middleware, controller gates, and policies handle access.
 */
class StoreTeacherCompensationRequest extends FormRequest
{
    use ValidatesTeacherCompensationPayload;

    /**
     * Resolve public teacher IDs to internal keys before validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(PublicIdResolver::resolveFields($this->all(), [
            'teacher_id' => User::class,
        ]));

        $this->prepareCompensationPayloadForValidation();
    }

    /**
     * Get validation rules for create teacher compensation requests.
     *
     * Important rules: sometimes rules support partial updates or optional filters; enum rules constrain values to the relevant model constants; exists rules require referenced records to be present; date and time ordering rules keep ranges consistent.
     *
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
