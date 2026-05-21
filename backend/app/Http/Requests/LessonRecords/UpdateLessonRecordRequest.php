<?php

namespace App\Http\Requests\LessonRecords;

use App\Models\LessonRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateLessonRecordRequest extends FormRequest
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
            'student_id' => ['sometimes', 'integer', 'exists:users,id'],
            'teacher_id' => ['sometimes', 'integer', 'exists:users,id'],
            'scheduled_date' => ['sometimes', 'date'],
            'start_time' => ['sometimes', 'date_format:H:i'],
            'end_time' => ['sometimes', 'date_format:H:i'],
            'meeting_link' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'lesson_type' => ['sometimes', 'string', Rule::in(LessonRecord::LESSON_TYPES)],
            'lesson_status' => ['sometimes', 'string', Rule::in(LessonRecord::STATUSES)],
            'lesson_notes' => ['sometimes', 'nullable', 'string'],
            'homework_details' => ['sometimes', 'nullable', 'string'],
            'homework_instructions' => ['sometimes', 'nullable', 'string'],
            'homework_due_date' => ['sometimes', 'nullable', 'date'],
            'attendance_status' => ['sometimes', 'nullable', 'string', Rule::in(LessonRecord::ATTENDANCE_STATUSES)],
            'is_completed' => ['sometimes', 'boolean'],
            'internal_remarks' => ['sometimes', 'nullable', 'string'],
        ];
    }

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
        });
    }
}
