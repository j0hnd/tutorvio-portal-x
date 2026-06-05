<?php

namespace App\Policies;

use App\Models\TeacherEarning;
use App\Models\User;

class TeacherEarningPolicy
{
    /**
     * Determine whether the user can list teacher earning records.
     *
     * Admins can list earnings. Staff need the Spatie permission
     * `teacher_earnings.view`. Teachers use the self-access rule and are
     * denied this admin list rule.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('teacher_earnings.view'));
    }

    /**
     * Determine whether the user can view a teacher earning record.
     *
     * Admins and staff with `teacher_earnings.view` can view any record.
     * Teachers must pass the self-access rule and own the earning record.
     * Other teachers, students, and staff without permission are denied.
     */
    public function view(User $user, TeacherEarning $teacherEarning): bool
    {
        if ($this->viewAny($user)) {
            return true;
        }

        return $this->viewOwn($user)
            && $user->hasRole('teacher')
            && (int) $teacherEarning->teacher_id === (int) $user->id;
    }

    /**
     * Determine whether the teacher can view their own earnings.
     *
     * Only teachers are eligible. Self-access is allowed when
     * `teacher_earnings.teacher_self_access_enabled` is enabled or the teacher
     * has `teacher_earnings.view_own`.
     */
    public function viewOwn(User $user): bool
    {
        if (! $user->hasRole('teacher')) {
            return false;
        }

        return (bool) config('teacher_earnings.teacher_self_access_enabled', false)
            || $user->can('teacher_earnings.view_own');
    }
}
