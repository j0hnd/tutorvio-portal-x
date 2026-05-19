<?php

namespace App\Http\Requests\Profile;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $targetUser = $this->route('user') ?: $user;

        // If target user is passed as an ID
        if (!$targetUser instanceof User) {
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

    public function rules(): array
    {
        $user = $this->user();
        $targetUser = $this->route('user') ?: $user;

        if (!$targetUser instanceof User) {
            $targetUser = User::findOrFail($targetUser);
        }

        $rules = [];
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
            }
        } elseif ($targetUser->hasRole('teacher')) {
            if ($user->id === $targetUser->id) {
                // Teacher updating their own profile
                $rules['teacher_profile'] = ['sometimes', 'array'];
                $rules['teacher_profile.bio'] = ['sometimes', 'string', 'nullable'];
                $rules['teacher_profile.specialization'] = ['sometimes', 'string', 'nullable'];
                $rules['teacher_profile.expertise'] = ['sometimes', 'string', 'nullable'];
                $rules['teacher_profile.teaching_availability'] = ['sometimes', 'array', 'nullable'];
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
}
