<?php

namespace App\Policies;

use App\Models\TeacherCompensation;
use App\Models\User;

class TeacherCompensationPolicy
{
    /**
     * Determine whether the user can list teacher compensation records.
     *
     * Admins can list records. Staff need the Spatie permission
     * `teacher_compensations.view`. Teachers and students are denied.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('teacher_compensations.view'));
    }

    /**
     * Determine whether the user can view a teacher compensation record.
     *
     * Detail access follows the list rule: admins are allowed and staff need
     * `teacher_compensations.view`. No teacher ownership exception applies.
     */
    public function view(User $user, TeacherCompensation $teacherCompensation): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can create teacher compensation records.
     *
     * Admins can create records. Staff need `teacher_compensations.manage`.
     * Teachers and students are denied.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('teacher_compensations.manage'));
    }

    /**
     * Determine whether the user can update a teacher compensation record.
     *
     * Admins can update records. Staff need `teacher_compensations.manage`.
     * Teachers and students are denied.
     */
    public function update(User $user, TeacherCompensation $teacherCompensation): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('teacher_compensations.manage'));
    }

    /**
     * Determine whether the user can delete or archive a compensation record.
     *
     * Deletion/archive follows the update rule: admins are allowed and staff
     * require `teacher_compensations.manage`.
     */
    public function delete(User $user, TeacherCompensation $teacherCompensation): bool
    {
        return $this->update($user, $teacherCompensation);
    }
}
