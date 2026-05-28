<?php

namespace App\Http\Requests\Reports;

use App\Reports\SchoolReportFilters;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class SchoolReportRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge($this->normalizedDateInput());
    }

    /**
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
        ];
    }

    public function filters(): SchoolReportFilters
    {
        return SchoolReportFilters::fromArray($this->validated());
    }

    /**
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
