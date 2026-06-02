<?php

namespace App\Http\Requests\LessonRecords;

use App\Models\LessonRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates update lesson record requests.
 *
 * Expected roles: Authenticated teachers, staff, or admins allowed by lesson record routes, controller checks, or policies.
 * Request-level authorize() documents any additional checks; otherwise route middleware, controller gates, and policies handle access.
 */
class UpdateLessonRecordRequest extends FormRequest
{
    /**
     * Determine whether the authenticated user can submit update lesson record requests. This request adds no request-local authorization beyond route middleware, controller gates, or policies.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Copy the homework_instructions alias into homework_details when the canonical field is absent.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('homework_instructions') && ! $this->has('homework_details')) {
            $this->merge([
                'homework_details' => $this->input('homework_instructions'),
            ]);
        }
    }

    /**
     * Get validation rules for update lesson record requests.
     *
     * Important rules: sometimes rules support partial updates or optional filters; enum rules constrain values to the relevant model constants; exists rules require referenced records to be present; distinct rules reject duplicate IDs in arrays.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'student_id' => ['sometimes', 'integer', 'exists:users,id'],
            'teacher_id' => ['sometimes', 'integer', 'exists:users,id'],
            'scheduled_date' => ['sometimes', 'date'],
            'start_time' => ['sometimes', 'date_format:H:i'],
            'end_time' => ['sometimes', 'date_format:H:i'],
            'meeting_link' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'meeting_provider' => ['sometimes', 'nullable', 'string', Rule::in(LessonRecord::MEETING_PROVIDERS)],
            'meeting_metadata' => ['sometimes', 'nullable', 'array'],
            'join_available_from' => ['sometimes', 'nullable', 'date'],
            'join_available_until' => ['sometimes', 'nullable', 'date'],
            'lesson_type' => ['sometimes', 'string', Rule::in(LessonRecord::LESSON_TYPES)],
            'lesson_status' => ['sometimes', 'string', Rule::in(LessonRecord::STATUSES)],
            'lesson_notes' => ['sometimes', 'nullable', 'string'],
            'homework_details' => ['sometimes', 'nullable', 'string'],
            'homework_instructions' => ['sometimes', 'nullable', 'string'],
            'homework_due_date' => ['sometimes', 'nullable', 'date'],
            'attendance_status' => ['sometimes', 'nullable', 'string', Rule::in(LessonRecord::ATTENDANCE_STATUSES)],
            'is_completed' => ['sometimes', 'boolean'],
            'internal_remarks' => ['sometimes', 'nullable', 'string'],
            'material_ids' => ['sometimes', 'array'],
            'material_ids.*' => ['integer', 'distinct', 'exists:materials,id'],
        ];
    }

    /**
     * Register after-validation checks that preserve time and join-window ordering during partial updates.
     *
     * @param  mixed  $validator
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $lessonRecord = $this->route('lessonRecord');

            if (! $lessonRecord instanceof LessonRecord) {
                return;
            }

            $startTime = $this->input('start_time', $lessonRecord->start_time);
            $endTime = $this->input('end_time', $lessonRecord->end_time);

            if ($startTime >= $endTime) {
                $validator->errors()->add('end_time', 'The end time field must be after start time.');
            }

            $joinAvailableFrom = $this->input('join_available_from', $lessonRecord->join_available_from);
            $joinAvailableUntil = $this->input('join_available_until', $lessonRecord->join_available_until);

            if ($joinAvailableFrom !== null && $joinAvailableUntil !== null && strtotime((string) $joinAvailableUntil) <= strtotime((string) $joinAvailableFrom)) {
                $validator->errors()->add('join_available_until', 'The join available until field must be after join available from.');
            }
        });
    }
}
