<?php

namespace App\Http\Requests\Reports;

use App\Models\CourseProgram;
use App\Models\User;
use App\Reports\SchoolReportFilters;
use App\Support\PublicIdResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * Validates shared school report filters.
 *
 * Expected roles: Admin or staff users with school report access unless the route narrows access further.
 * Request-level authorize() documents any additional checks; otherwise route middleware, controller gates, and policies handle access.
 */
class SchoolReportRequest extends FormRequest
{
    /**
     * Normalize parseable report date filters to Y-m-d before validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            ...$this->normalizedDateInput(),
            ...PublicIdResolver::resolveFields($this->all(), [
                'teacher_id' => User::class,
                'student_id' => User::class,
                'course_id' => CourseProgram::class,
            ]),
        ]);
    }

    /**
     * Get validation rules for shared school report filters.
     *
     * Important rules: sometimes rules support partial updates or optional filters; exists rules require referenced records to be present; date and time ordering rules keep ranges consistent; regex rules restrict filter tokens to safe identifier characters.
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
            'course_id' => ['sometimes', 'integer', 'exists:course_programs,id'],
            'status' => ['sometimes', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Build typed report filters from validated query parameters.
     */
    public function filters(): SchoolReportFilters
    {
        return SchoolReportFilters::fromArray($this->validated());
    }

    /**
     * Return normalized pagination values for school report responses.
     *
     * @return array{page: int, per_page: int}
     */
    public function pagination(): array
    {
        $validated = $this->validated();

        return [
            'page' => (int) ($validated['page'] ?? 1),
            'per_page' => (int) ($validated['per_page'] ?? 50),
        ];
    }

    /**
     * Normalize parseable date filters without failing validation for unparseable values.
     *
     * @return array<string, string>
     */
    private function normalizedDateInput(): array
    {
        $dates = [];

        foreach (['date_from', 'date_to'] as $key) {
            if (! $this->filled($key)) {
                continue;
            }

            try {
                $dates[$key] = Carbon::parse($this->input($key))->toDateString();
            } catch (\Throwable) {
                continue;
            }
        }

        return $dates;
    }
}
