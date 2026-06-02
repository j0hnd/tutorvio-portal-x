<?php

namespace App\Http\Requests\AcademicRecords;

use App\Models\AcademicRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAcademicRecordRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'student_id' => ['sometimes', 'integer', 'exists:users,id'],
            'teacher_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'course_program_id' => ['sometimes', 'nullable', 'integer', 'exists:course_programs,id'],
            'lesson_id' => ['sometimes', 'nullable', 'integer', 'exists:lessons,id'],
            'class_schedule_id' => ['sometimes', 'nullable', 'integer', 'exists:class_schedules,id'],
            'record_type' => ['sometimes', 'string', Rule::in(AcademicRecord::RECORD_TYPES)],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'status' => ['sometimes', 'string', Rule::in(AcademicRecord::STATUSES)],
            'recorded_on' => ['sometimes', 'nullable', 'date'],
            'data' => ['sometimes', 'nullable', 'array'],
            'student_level' => ['sometimes', 'nullable', 'string', 'max:255'],
            'placement_result' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'course_program_history' => ['sometimes', 'nullable', 'array'],
            'attendance_summary' => ['sometimes', 'nullable', 'array'],
            'progress_summary' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'teacher_remarks' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'certificates' => ['sometimes', 'nullable', 'array'],
            'completion_notes' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'internal_notes' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'approved_by' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'approved_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
