<?php

namespace App\Policies;

use App\Models\Scheduling\Holiday;
use App\Models\User;

class HolidayPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('classes.view') || $user->can('holidays.view');
    }

    public function view(User $user, Holiday $holiday): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'staff']) && ($user->can('classes.create') || $user->can('holidays.manage'));
    }

    public function update(User $user, Holiday $holiday): bool
    {
        return $user->hasAnyRole(['admin', 'staff']) && ($user->can('classes.update') || $user->can('holidays.manage'));
    }

    public function delete(User $user, Holiday $holiday): bool
    {
        return $user->hasAnyRole(['admin', 'staff']) && ($user->can('classes.delete') || $user->can('holidays.manage'));
    }
}
