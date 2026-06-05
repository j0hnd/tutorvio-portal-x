<?php

namespace App\Policies;

use App\Models\Scheduling\Holiday;
use App\Models\User;

class HolidayPolicy
{
    /**
     * Determine whether the user can list holidays.
     *
     * Access requires either Spatie permission `classes.view` or
     * `holidays.view`. Users without both permissions are denied.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('classes.view') || $user->can('holidays.view');
    }

    /**
     * Determine whether the user can view a holiday.
     *
     * Holiday detail access follows the list rule and has no ownership or
     * assignment exception.
     */
    public function view(User $user, Holiday $holiday): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can create holidays.
     *
     * Admin or staff role is required, plus either `classes.create` or
     * `holidays.manage`. Teachers and students are denied.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'staff']) && ($user->can('classes.create') || $user->can('holidays.manage'));
    }

    /**
     * Determine whether the user can update a holiday.
     *
     * Admin or staff role is required, plus either `classes.update` or
     * `holidays.manage`. Teachers and students are denied.
     */
    public function update(User $user, Holiday $holiday): bool
    {
        return $user->hasAnyRole(['admin', 'staff']) && ($user->can('classes.update') || $user->can('holidays.manage'));
    }

    /**
     * Determine whether the user can delete a holiday.
     *
     * Admin or staff role is required, plus either `classes.delete` or
     * `holidays.manage`. Teachers and students are denied.
     */
    public function delete(User $user, Holiday $holiday): bool
    {
        return $user->hasAnyRole(['admin', 'staff']) && ($user->can('classes.delete') || $user->can('holidays.manage'));
    }
}
