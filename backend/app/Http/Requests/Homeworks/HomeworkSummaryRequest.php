<?php

namespace App\Http\Requests\Homeworks;

use App\Models\Homework;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates homework summary requests.
 *
 * Expected roles: Authenticated students, teachers, staff, or admins allowed by homework routes, controller checks, or policies.
 * Request-level authorize() documents any additional checks; otherwise route middleware, controller gates, and policies handle access.
 */
class HomeworkSummaryRequest extends FormRequest
{
    /**
     * Get validation rules for homework summary requests.
     *
     * Important rules: sometimes rules support partial updates or optional filters; enum rules constrain values to the relevant model constants; exists rules require referenced records to be present; date and time ordering rules keep ranges consistent.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['sometimes', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'teacher_id' => ['sometimes', 'integer', 'exists:users,id'],
            'student_id' => ['sometimes', 'integer', 'exists:users,id'],
            'status' => ['sometimes', 'string', Rule::in(Homework::STATUSES)],
            'course' => ['sometimes', 'string', 'max:255'],
            'level' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
