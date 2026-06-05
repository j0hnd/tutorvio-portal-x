<?php

namespace App\Http\Requests\TeacherAssignments;

use App\Models\TeacherStudentAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates update teacher student assignment requests.
 *
 * Expected roles: Admins, or staff users with teacher assignment management access.
 * Request-level authorize() documents any additional checks; otherwise route middleware, controller gates, and policies handle access.
 */
class UpdateTeacherStudentAssignmentRequest extends FormRequest
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
     * Get validation rules for update teacher student assignment requests.
     *
     * Important rules: enum rules constrain values to the relevant model constants; partial update rules validate only supplied fields unless a supplied field is explicitly required.
     *
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
