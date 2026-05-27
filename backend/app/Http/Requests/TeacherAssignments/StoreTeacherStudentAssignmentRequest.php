<?php

namespace App\Http\Requests\TeacherAssignments;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreTeacherStudentAssignmentRequest extends FormRequest
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
            'student_id' => ['required', 'integer', 'exists:users,id'],
            'teacher_id' => ['required', 'integer', 'different:student_id', 'exists:users,id'],
            'assigned_at' => ['sometimes', 'date'],
            'reason' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'previous_assignment_notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $student = User::query()->find($this->integer('student_id'));
            $teacher = User::query()->find($this->integer('teacher_id'));

            if ($student && ($student->status !== User::STATUS_ACTIVE || ! $student->hasRole('student'))) {
                $validator->errors()->add('student_id', 'The selected student must be an active student.');
            }

            if ($teacher && ($teacher->status !== User::STATUS_ACTIVE || ! $teacher->hasRole('teacher'))) {
                $validator->errors()->add('teacher_id', 'The selected teacher must be an active teacher.');
            }

            if ($teacher && $this->user()?->hasRole('teacher') && (int) $this->user()->id === (int) $teacher->id) {
                $validator->errors()->add('teacher_id', 'Teachers cannot assign students to themselves.');
            }
        });
    }
}
