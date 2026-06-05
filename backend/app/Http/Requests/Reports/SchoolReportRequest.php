<?php

namespace App\Http\Requests\Reports;

use App\Models\CourseProgram;
use App\Models\User;
use App\Reports\SchoolReportFilters;
use App\Support\PublicIdResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

/**
 * Validates shared school report filters.
 *
 * Expected roles: Admin or staff users with school report access unless the route narrows access further.
 * Request-level authorize() documents any additional checks; otherwise route middleware, controller gates, and policies handle access.
 */
class SchoolReportRequest extends FormRequest
{
    public const MAX_DATE_RANGE_DAYS = 366;

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
     * Enforce a bounded report date window when callers provide both bounds.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('date_from') || ! $this->filled('date_to')) {
                return;
            }

            try {
                $dateFrom = Carbon::createFromFormat('Y-m-d', (string) $this->input('date_from'))->startOfDay();
                $dateTo = Carbon::createFromFormat('Y-m-d', (string) $this->input('date_to'))->startOfDay();
            } catch (\Throwable) {
                return;
            }

            if ($dateFrom->diffInDays($dateTo) > self::MAX_DATE_RANGE_DAYS) {
                $validator->errors()->add(
                    'date_to',
                    'The report date range may not be greater than '.self::MAX_DATE_RANGE_DAYS.' days.'
                );
            }
        });
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
