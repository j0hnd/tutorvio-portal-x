<?php

namespace App\Policies;

use App\Models\CourseProgram;
use App\Models\User;

class CourseProgramPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'teacher', 'student'])
            || ($user->hasRole('staff') && $user->can('course_programs.view'));
    }

    public function view(User $user, CourseProgram $courseProgram): bool
    {
        if ($user->hasRole('admin') || ($user->hasRole('staff') && $user->can('course_programs.view'))) {
            return true;
        }

        return CourseProgram::query()
            ->whereKey($courseProgram->getKey())
            ->visibleTo($user)
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('course_programs.create'));
    }

    public function update(User $user, CourseProgram $courseProgram): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('course_programs.update'));
    }

    public function delete(User $user, CourseProgram $courseProgram): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('course_programs.delete'));
    }

    public function viewStudentAssignments(User $user, CourseProgram $courseProgram): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('course_programs.view'));
    }
}
