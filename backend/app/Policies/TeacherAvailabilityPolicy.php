<?php

namespace App\Policies;

use App\Models\Scheduling\TeacherAvailability;
use App\Models\Scheduling\TeacherUnavailableDate;
use App\Models\User;

class TeacherAvailabilityPolicy
{
    /**
     * Determine whether the user can list teacher availability.
     *
     * The user must be an admin, staff member, teacher, or student, and must
     * have either `classes.view` or `availability.view`. Other roles or users
     * without those Spatie permissions are denied.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'staff', 'teacher', 'student'])
            && ($user->can('classes.view') || $user->can('availability.view'));
    }

    /**
     * Determine whether the user can create teacher availability.
     *
     * Only teachers with `availability.manage` can create availability.
     * Admin, staff, and student roles are denied by this policy.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('teacher') && $user->can('availability.manage');
    }

    /**
     * Determine whether the user can view availability or unavailable dates.
     *
     * Admins and staff can view all entries. Teachers can view entries for
     * their own teacher id. Students can view entries for their assigned
     * teacher. Others are denied.
     */
    public function view(User $user, TeacherAvailability|TeacherUnavailableDate $availability): bool
    {
        return $user->hasAnyRole(['admin', 'staff'])
            || $availability->teacher_id === $user->id
            || ($user->hasRole('student') && $user->studentProfile?->assigned_teacher_id === $availability->teacher_id);
    }

    /**
     * Determine whether the user can update availability or unavailable dates.
     *
     * Only teachers with `availability.manage` can update, and only for their
     * own teacher id. Admins, staff, students, and other teachers are denied.
     */
    public function update(User $user, TeacherAvailability|TeacherUnavailableDate $availability): bool
    {
        return $user->hasRole('teacher')
            && $user->can('availability.manage')
            && $availability->teacher_id === $user->id;
    }

    /**
     * Determine whether the user can delete availability or unavailable dates.
     *
     * Deletion follows the update rule: the user must be the owning teacher
     * and have `availability.manage`.
     */
    public function delete(User $user, TeacherAvailability|TeacherUnavailableDate $availability): bool
    {
        return $this->update($user, $availability);
    }
}
