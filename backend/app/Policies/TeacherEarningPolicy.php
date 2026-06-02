<?php

namespace App\Policies;

use App\Models\TeacherEarning;
use App\Models\User;

class TeacherEarningPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('teacher_earnings.view'));
    }

    public function view(User $user, TeacherEarning $teacherEarning): bool
    {
        if ($this->viewAny($user)) {
            return true;
        }

        return $this->viewOwn($user)
            && $user->hasRole('teacher')
            && (int) $teacherEarning->teacher_id === (int) $user->id;
    }

    public function viewOwn(User $user): bool
    {
        if (! $user->hasRole('teacher')) {
            return false;
        }

        return (bool) config('teacher_earnings.teacher_self_access_enabled', false)
            || $user->can('teacher_earnings.view_own');
    }
}
