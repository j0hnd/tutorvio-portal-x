<?php

namespace App\Http\Requests\LessonRecords;

use App\Models\LessonRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLessonRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('homework_instructions') && ! $this->has('homework_details')) {
            $this->merge([
                'homework_details' => $this->input('homework_instructions'),
            ]);
        }
    }

    /**
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
