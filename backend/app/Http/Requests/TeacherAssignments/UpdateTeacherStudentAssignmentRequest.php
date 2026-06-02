<?php

namespace App\Http\Requests\TeacherAssignments;

use App\Models\TeacherStudentAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeacherStudentAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->hasRole('admin')
            || ($user?->hasRole('staff') && $user->can('teacher_assignments.manage'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(TeacherStudentAssignment::STATUSES)],
            'reason' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'ended_at' => ['nullable', 'date'],
        ];
    }
}
