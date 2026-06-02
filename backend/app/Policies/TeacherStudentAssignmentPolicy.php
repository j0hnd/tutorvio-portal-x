<?php

namespace App\Policies;

use App\Models\TeacherStudentAssignment;
use App\Models\User;

class TeacherStudentAssignmentPolicy
{
    /**
     * Determine whether the user can list teacher-student assignments.
     *
     * Admins can list assignments. Staff need the Spatie permission
     * `teacher_assignments.view`. Teachers and students are denied this admin
     * assignment list rule.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('teacher_assignments.view'));
    }

    /**
     * Determine whether the user can view a teacher-student assignment.
     *
     * Detail access follows the list rule: admins are allowed and staff need
     * `teacher_assignments.view`. No teacher/student ownership exception
     * applies in this policy.
     */
    public function view(User $user, TeacherStudentAssignment $teacherStudentAssignment): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can create teacher-student assignments.
     *
     * Admins can create assignments. Staff need `teacher_assignments.manage`.
     * Teachers and students are denied.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('teacher_assignments.manage'));
    }

    /**
     * Determine whether the user can update teacher-student assignments.
     *
     * Admins can update assignments. Staff need `teacher_assignments.manage`.
     * Teachers and students are denied.
     */
    public function update(User $user, TeacherStudentAssignment $teacherStudentAssignment): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('teacher_assignments.manage'));
    }
}
