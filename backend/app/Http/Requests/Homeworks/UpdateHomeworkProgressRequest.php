<?php

namespace App\Http\Requests\Homeworks;

use App\Models\Homework;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates update homework progress requests.
 *
 * Expected roles: Authenticated students, teachers, staff, or admins allowed by homework routes, controller checks, or policies.
 * Request-level authorize() documents any additional checks; otherwise route middleware, controller gates, and policies handle access.
 */
class UpdateHomeworkProgressRequest extends FormRequest
{
    /**
     * Get validation rules for update homework progress requests.
     *
     * Important rules: enum rules constrain values to the relevant model constants; partial update rules validate only supplied fields unless a supplied field is explicitly required.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in([
                Homework::STATUS_IN_PROGRESS,
                Homework::STATUS_COMPLETED,
            ])],
        ];
    }
}
