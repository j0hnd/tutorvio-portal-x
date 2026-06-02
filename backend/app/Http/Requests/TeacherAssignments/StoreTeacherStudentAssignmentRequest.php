<?php

namespace App\Http\Requests\TeacherAssignments;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates create teacher student assignment requests.
 *
 * Expected roles: Admins, or staff users with teacher assignment management access.
 * Request-level authorize() documents any additional checks; otherwise route middleware, controller gates, and policies handle access.
 */
class StoreTeacherStudentAssignmentRequest extends FormRequest
{
    /**
     * Determine whether the authenticated user can manage teacher-student assignments. Admins pass, and staff must have assignment management access.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->hasRole('admin')
            || ($user?->hasRole('staff') && $user->can('teacher_assignments.manage'));
    }

    /**
     * Get validation rules for create teacher student assignment requests.
     *
     * Important rules: sometimes rules support partial updates or optional filters; enum rules constrain values to the relevant model constants; exists rules require referenced records to be present; required rules define the minimum payload for creation.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'student_id' => [$this->route('student') ? 'sometimes' : 'required', 'integer', 'exists:users,id'],
            'teacher_id' => ['required', 'integer', 'different:student_id', 'exists:users,id'],
            'assigned_at' => ['sometimes', 'date'],
            'reason' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'previous_assignment_notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    /**
     * Register after-validation checks for cross-field or database-backed constraints on create teacher student assignment requests.
     *
     * @param  mixed  $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $student = $this->route('student') instanceof User
                ? $this->route('student')
                : User::query()->find($this->integer('student_id'));
            $teacher = User::query()->find($this->integer('teacher_id'));

            if ($student && ($student->status !== User::STATUS_ACTIVE || ! $student->hasRole('student'))) {
                $validator->errors()->add('student_id', 'The selected student must be an active student.');
            }

            if ($teacher && ($teacher->status !== User::STATUS_ACTIVE || ! $teacher->hasRole('teacher'))) {
                $validator->errors()->add('teacher_id', 'The selected teacher must be an active teacher.');
            }

            if ($student && $teacher && (int) $student->id === (int) $teacher->id) {
                $validator->errors()->add('teacher_id', 'The selected teacher and student must be different users.');
            }

            if ($teacher && $this->user()?->hasRole('teacher') && (int) $this->user()->id === (int) $teacher->id) {
                $validator->errors()->add('teacher_id', 'Teachers cannot assign students to themselves.');
            }
        });
    }
}
