<?php

namespace App\Policies;

use App\Models\TeacherCompensation;
use App\Models\User;

class TeacherCompensationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('teacher_compensations.view'));
    }

    public function view(User $user, TeacherCompensation $teacherCompensation): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('teacher_compensations.manage'));
    }

    public function update(User $user, TeacherCompensation $teacherCompensation): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('teacher_compensations.manage'));
    }

    public function delete(User $user, TeacherCompensation $teacherCompensation): bool
    {
        return $this->update($user, $teacherCompensation);
    }
}
