<?php

namespace App\Policies;

use App\Models\CourseType;
use App\Models\User;

class CourseTypePolicy
{
    /**
     * Determine whether the user can list course types.
     *
     * Course-type management is admin-only. Staff, teachers, and students are
     * denied; no Spatie permission grants access here.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can view a course type.
     *
     * Course-type detail access is admin-only. Staff, teachers, and students
     * are denied; no ownership rule applies.
     */
    public function view(User $user, CourseType $courseType): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can create a course type.
     *
     * Creation is admin-only. No Spatie permission or ownership rule grants
     * access to other roles.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can update a course type.
     *
     * Updates are admin-only. Staff, teachers, and students are denied.
     */
    public function update(User $user, CourseType $courseType): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can delete or archive a course type.
     *
     * Deletion/archive is admin-only. No assignment or permission override is
     * available for non-admin users.
     */
    public function delete(User $user, CourseType $courseType): bool
    {
        return $user->hasRole('admin');
    }
}
