<?php

namespace App\Policies;

use App\Models\Scheduling\ScheduleReminder;
use App\Models\User;

class ScheduleReminderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('classes.view') || $user->can('reminders.view');
    }

    public function view(User $user, ScheduleReminder $scheduleReminder): bool
    {
        return $user->hasAnyRole(['admin', 'staff']) || $scheduleReminder->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'staff']) && ($user->can('classes.create') || $user->can('reminders.manage'));
    }

    public function update(User $user, ScheduleReminder $scheduleReminder): bool
    {
        return $user->hasAnyRole(['admin', 'staff']) && ($user->can('classes.update') || $user->can('reminders.manage'));
    }

    public function delete(User $user, ScheduleReminder $scheduleReminder): bool
    {
        return $user->hasAnyRole(['admin', 'staff']) && ($user->can('classes.delete') || $user->can('reminders.manage'));
    }
}
