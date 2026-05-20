<?php

namespace App\Policies;

use App\Models\Scheduling\TeacherAvailability;
use App\Models\Scheduling\TeacherUnavailableDate;
use App\Models\User;

class TeacherAvailabilityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('classes.view') || $user->can('availability.view');
    }

    public function create(User $user): bool
    {
        return $user->can('classes.update') || $user->can('availability.manage');
    }

    public function view(User $user, TeacherAvailability|TeacherUnavailableDate $availability): bool
    {
        return $user->hasAnyRole(['admin', 'staff']) || $availability->teacher_id === $user->id;
    }

    public function update(User $user, TeacherAvailability|TeacherUnavailableDate $availability): bool
    {
        return ($user->can('classes.update') || $user->can('availability.manage'))
            && ($user->hasAnyRole(['admin', 'staff']) || $availability->teacher_id === $user->id);
    }

    public function delete(User $user, TeacherAvailability|TeacherUnavailableDate $availability): bool
    {
        return $this->update($user, $availability);
    }
}
