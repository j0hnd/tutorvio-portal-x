<?php

namespace App\Policies;

use App\Models\Scheduling\ClassSchedule;
use App\Models\User;

class ClassSchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('classes.view') || $user->can('schedules.view');
    }

    public function view(User $user, ClassSchedule $classSchedule): bool
    {
        return $this->managesSchedules($user)
            || ($user->hasRole('teacher') && $classSchedule->teacher_id === $user->id)
            || ($user->hasRole('student') && $classSchedule->student_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->can('classes.create') || $user->can('schedules.create');
    }

    public function update(User $user, ClassSchedule $classSchedule): bool
    {
        return ($user->can('classes.update') || $user->can('schedules.update'))
            && ($this->managesSchedules($user) || $classSchedule->teacher_id === $user->id);
    }

    public function delete(User $user, ClassSchedule $classSchedule): bool
    {
        return ($user->can('classes.delete') || $user->can('schedules.delete')) && $this->managesSchedules($user);
    }

    public function cancel(User $user, ClassSchedule $classSchedule): bool
    {
        return $this->update($user, $classSchedule);
    }

    public function reschedule(User $user, ClassSchedule $classSchedule): bool
    {
        return $this->update($user, $classSchedule);
    }

    private function managesSchedules(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'staff']);
    }
}
