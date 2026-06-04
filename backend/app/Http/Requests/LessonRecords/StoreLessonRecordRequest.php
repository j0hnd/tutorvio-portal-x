<?php

namespace App\Http\Requests\LessonRecords;

use App\Models\LessonRecord;
use App\Models\User;
use App\Support\PublicIdResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates create lesson record requests.
 *
 * Expected roles: Authenticated teachers, staff, or admins allowed by lesson record routes, controller checks, or policies.
 * Request-level authorize() documents any additional checks; otherwise route middleware, controller gates, and policies handle access.
 */
class StoreLessonRecordRequest extends FormRequest
{
    /**
     * Determine whether the authenticated user can submit create lesson record requests. This request adds no request-local authorization beyond route middleware, controller gates, or policies.
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
        $this->merge(PublicIdResolver::resolveFields($this->all(), [
            'student_id' => User::class,
            'teacher_id' => User::class,
        ]));

        if ($this->has('homework_instructions') && ! $this->has('homework_details')) {
            $this->merge([
                'homework_details' => $this->input('homework_instructions'),
            ]);
        }
    }

    /**
     * Get validation rules for create lesson record requests.
     *
     * Important rules: sometimes rules support partial updates or optional filters; enum rules constrain values to the relevant model constants; exists rules require referenced records to be present; date and time ordering rules keep ranges consistent.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:users,id'],
            'teacher_id' => ['required', 'integer', 'exists:users,id'],
            'scheduled_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'meeting_link' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'meeting_provider' => ['sometimes', 'nullable', 'string', Rule::in(LessonRecord::MEETING_PROVIDERS)],
            'meeting_metadata' => ['sometimes', 'nullable', 'array'],
            'join_available_from' => ['sometimes', 'nullable', 'date'],
            'join_available_until' => ['sometimes', 'nullable', 'date', 'after:join_available_from'],
            'lesson_type' => ['required', 'string', Rule::in(LessonRecord::LESSON_TYPES)],
            'lesson_status' => ['required', 'string', Rule::in(LessonRecord::STATUSES)],
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
}
