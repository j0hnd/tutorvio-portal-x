<?php

namespace App\Policies;

use App\Models\CourseProgram;
use App\Models\User;

class CourseProgramPolicy
{
    /**
     * Determine whether the user can list course programs.
     *
     * Admins, teachers, and students can list programs. Staff need the Spatie
     * permission `course_programs.view`. Visibility filtering is applied by
     * the course-program query scope.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'teacher', 'student'])
            || ($user->hasRole('staff') && $user->can('course_programs.view'));
    }

    /**
     * Determine whether the user can view a course program.
     *
     * Admins and staff with `course_programs.view` can view any program.
     * Teachers and students must pass the model's `visibleTo` assignment and
     * enrollment rules. Programs not visible through that scope are denied.
     */
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

    /**
     * Determine whether the user can create a course program.
     *
     * Admins can create programs. Staff need `course_programs.create`.
     * Teachers and students are denied.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('course_programs.create'));
    }

    /**
     * Determine whether the user can update a course program.
     *
     * Admins can update programs. Staff need `course_programs.update`.
     * Teachers and students are denied.
     */
    public function update(User $user, CourseProgram $courseProgram): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('course_programs.update'));
    }

    /**
     * Determine whether the user can delete or archive a course program.
     *
     * Admins can delete programs. Staff need `course_programs.delete`.
     * Teachers and students are denied.
     */
    public function delete(User $user, CourseProgram $courseProgram): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('course_programs.delete'));
    }

    /**
     * Determine whether the user can view student assignments for a program.
     *
     * Assignment details are limited to admins and staff with
     * `course_programs.view`. Teachers and students are denied this admin
     * assignment view even when they can view the course program itself.
     */
    public function viewStudentAssignments(User $user, CourseProgram $courseProgram): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('course_programs.view'));
    }
}
