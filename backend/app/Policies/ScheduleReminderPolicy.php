<?php

namespace App\Policies;

use App\Models\Scheduling\ScheduleReminder;
use App\Models\User;

class ScheduleReminderPolicy
{
    /**
     * Determine whether the user can list schedule reminders.
     *
     * Access requires either Spatie permission `classes.view` or
     * `reminders.view`. Users without both permissions are denied.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('classes.view') || $user->can('reminders.view');
    }

    /**
     * Determine whether the user can view a schedule reminder.
     *
     * Admin and staff users can view reminders. Other users can view only
     * reminders assigned to their own user id. Unassigned users are denied.
     */
    public function view(User $user, ScheduleReminder $scheduleReminder): bool
    {
        return $user->hasAnyRole(['admin', 'staff']) || $scheduleReminder->user_id === $user->id;
    }

    /**
     * Determine whether the user can create a schedule reminder.
     *
     * Admin or staff role is required, plus either `classes.create` or
     * `reminders.manage`. Teachers and students are denied by this policy.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'staff']) && ($user->can('classes.create') || $user->can('reminders.manage'));
    }

    /**
     * Determine whether the user can update a schedule reminder.
     *
     * Admin or staff role is required, plus either `classes.update` or
     * `reminders.manage`. Reminder ownership does not grant update access.
     */
    public function update(User $user, ScheduleReminder $scheduleReminder): bool
    {
        return $user->hasAnyRole(['admin', 'staff']) && ($user->can('classes.update') || $user->can('reminders.manage'));
    }

    /**
     * Determine whether the user can delete a schedule reminder.
     *
     * Admin or staff role is required, plus either `classes.delete` or
     * `reminders.manage`. Reminder ownership does not grant delete access.
     */
    public function delete(User $user, ScheduleReminder $scheduleReminder): bool
    {
        return $user->hasAnyRole(['admin', 'staff']) && ($user->can('classes.delete') || $user->can('reminders.manage'));
    }
}
