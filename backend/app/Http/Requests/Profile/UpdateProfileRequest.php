<?php

namespace App\Http\Requests\Profile;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates update profile requests.
 *
 * Expected roles: The target user, admins, staff with user update access, or assigned teachers for limited student profile updates.
 * Request-level authorize() documents any additional checks; otherwise route middleware, controller gates, and policies handle access.
 */
class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine whether the authenticated user can update the target profile.
     *
     * Allows self updates, admins, staff with user update access, and assigned
     * teachers for limited student profile updates.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $targetUser = $this->route('user') ?: $user;

        // If target user is passed as an ID
        if (! $targetUser instanceof User) {
            $targetUser = User::findOrFail($targetUser);
        }

        // 1. User can update their own profile
        if ($user->id === $targetUser->id) {
            return true;
        }

        // 2. Admin can update any profile
        if ($user->hasRole('admin')) {
            return true;
        }

        // 3. Staff can update if they have 'users.update' permission
        if ($user->hasRole('staff') && $user->can('users.update')) {
            return true;
        }

        // 4. Teacher can update their assigned student's profile (checked later for specific fields)
        if ($user->hasRole('teacher') && $targetUser->hasRole('student')) {
            $isAssigned = $targetUser->studentProfile()->where('assigned_teacher_id', $user->id)->exists();
            if ($isAssigned) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get validation rules for update profile requests.
     *
     * Important rules: sometimes rules support partial updates or optional filters; prohibited rules protect fields that this role or request must not change; exists rules require referenced records to be present.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->user();
        $targetUser = $this->route('user') ?: $user;

        if (! $targetUser instanceof User) {
            $targetUser = User::findOrFail($targetUser);
        }

        $rules = $this->protectedFieldRules();
        $isAdminOrStaff = $user->hasRole(['admin', 'staff']);
        $isTeacherAssigned = false;

        if ($user->hasRole('teacher') && $targetUser->hasRole('student')) {
            $isTeacherAssigned = $targetUser->studentProfile()->where('assigned_teacher_id', $user->id)->exists();
        }

        // Basic user fields
        if ($user->id === $targetUser->id || $isAdminOrStaff) {
            $rules['name'] = ['sometimes', 'string', 'max:255'];
            $rules['phone'] = ['sometimes', 'string', 'max:20', 'nullable'];
            $rules['timezone'] = ['sometimes', 'string', 'max:50', 'timezone', 'nullable'];
            // Students can't change email easily usually, but let's allow basic fields if authorized
        }

        // Profile specific fields
        if ($targetUser->hasRole('student')) {
            if ($user->id === $targetUser->id) {
                // Student updating their own profile
                $rules['student_profile'] = ['sometimes', 'array'];
                $rules['student_profile.preferences'] = ['sometimes', 'string', 'nullable'];
                $rules['student_profile.goals'] = ['sometimes', 'string', 'nullable'];
                $rules['student_profile.learning_concerns'] = ['sometimes', 'string', 'nullable'];
                $rules += $this->protectedStudentProfileRules();
            } elseif ($isAdminOrStaff) {
                // Admin/Staff updating student profile
                $rules['student_profile'] = ['sometimes', 'array'];
                $rules['student_profile.english_level'] = ['sometimes', 'string', 'nullable'];
                $rules['student_profile.current_level'] = ['sometimes', 'string', 'nullable'];
                $rules['student_profile.course'] = ['sometimes', 'string', 'nullable'];
                $rules['student_profile.class_type'] = ['sometimes', 'string', 'nullable'];
                $rules['student_profile.start_date'] = ['sometimes', 'date', 'nullable'];
                $rules['student_profile.notes'] = ['sometimes', 'string', 'nullable'];
                $rules['student_profile.internal_notes'] = ['sometimes', 'string', 'nullable'];
                $rules['student_profile.teacher_notes'] = ['sometimes', 'string', 'nullable'];
                $rules['student_profile.assigned_teacher_id'] = ['sometimes', 'integer', 'exists:users,id', 'nullable'];
            } elseif ($isTeacherAssigned) {
                // Teacher updating assigned student profile
                $rules['student_profile'] = ['sometimes', 'array'];
                $rules['student_profile.teacher_notes'] = ['sometimes', 'string', 'nullable'];
                $rules['student_profile.assigned_teacher_id'] = ['prohibited'];
                $rules['student_profile.internal_notes'] = ['prohibited'];
            }
        } elseif ($targetUser->hasRole('teacher')) {
            if ($user->id === $targetUser->id) {
                // Teacher updating their own profile
                $rules['teacher_profile'] = ['sometimes', 'array'];
                $rules['teacher_profile.bio'] = ['sometimes', 'string', 'nullable'];
                $rules['teacher_profile.specialization'] = ['sometimes', 'string', 'nullable'];
                $rules['teacher_profile.expertise'] = ['sometimes', 'string', 'nullable'];
                $rules['teacher_profile.teaching_availability'] = ['sometimes', 'array', 'nullable'];
                $rules += $this->protectedTeacherProfileRules();
            } elseif ($isAdminOrStaff) {
                // Admin/Staff updating teacher profile
                $rules['teacher_profile'] = ['sometimes', 'array'];
                $rules['teacher_profile.bio'] = ['sometimes', 'string', 'nullable'];
                $rules['teacher_profile.specialization'] = ['sometimes', 'string', 'nullable'];
                $rules['teacher_profile.expertise'] = ['sometimes', 'string', 'nullable'];
                $rules['teacher_profile.class_load'] = ['sometimes', 'integer', 'nullable'];
                $rules['teacher_profile.teaching_availability'] = ['sometimes', 'array', 'nullable'];
                $rules['teacher_profile.performance_summary'] = ['sometimes', 'string', 'nullable'];
                $rules['teacher_profile.internal_status'] = ['sometimes', 'string', 'nullable'];
                $rules['teacher_profile.teaching_notes'] = ['sometimes', 'string', 'nullable'];
                $rules['teacher_profile.internal_remarks'] = ['sometimes', 'string', 'nullable'];
                $rules['teacher_profile.document_contract_status'] = ['sometimes', 'string', 'nullable'];
            }
        } elseif ($targetUser->hasRole(['admin', 'staff'])) {
            if ($isAdminOrStaff) {
                $rules['staff_profile'] = ['sometimes', 'array'];
                $rules['staff_profile.department'] = ['sometimes', 'string', 'nullable'];
                $rules['staff_profile.access_limitations'] = ['sometimes', 'string', 'nullable'];
            }
        }

        return $rules;
    }

    /**
     * Return protected-field rules for update profile requests.
     *
     * @return array<string, array<int, string>>
     */
    private function protectedFieldRules(): array
    {
        return [
            'role' => ['prohibited'],
            'roles' => ['prohibited'],
            'permissions' => ['prohibited'],
            'status' => ['prohibited'],
            'created_by' => ['prohibited'],
            'updated_by' => ['prohibited'],
            'email_verified_at' => ['prohibited'],
            'invited_at' => ['prohibited'],
            'activated_at' => ['prohibited'],
        ];
    }

    /**
     * Return protected-field rules for update profile requests.
     *
     * @return array<string, array<int, string>>
     */
    private function protectedStudentProfileRules(): array
    {
        return [
            'student_profile.assigned_teacher_id' => ['prohibited'],
            'student_profile.notes' => ['prohibited'],
            'student_profile.internal_notes' => ['prohibited'],
            'student_profile.teacher_notes' => ['prohibited'],
        ];
    }

    /**
     * Return protected-field rules for update profile requests.
     *
     * @return array<string, array<int, string>>
     */
    private function protectedTeacherProfileRules(): array
    {
        return [
            'teacher_profile.class_load' => ['prohibited'],
            'teacher_profile.performance_summary' => ['prohibited'],
            'teacher_profile.internal_status' => ['prohibited'],
            'teacher_profile.teaching_notes' => ['prohibited'],
            'teacher_profile.internal_remarks' => ['prohibited'],
            'teacher_profile.document_contract_status' => ['prohibited'],
        ];
    }
}
