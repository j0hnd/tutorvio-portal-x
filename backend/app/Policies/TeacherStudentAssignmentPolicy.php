<?php

namespace App\Policies;

use App\Models\TeacherStudentAssignment;
use App\Models\User;

class TeacherStudentAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('teacher_assignments.view'));
    }

    public function view(User $user, TeacherStudentAssignment $teacherStudentAssignment): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('teacher_assignments.manage'));
    }

    public function update(User $user, TeacherStudentAssignment $teacherStudentAssignment): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('teacher_assignments.manage'));
    }
}
