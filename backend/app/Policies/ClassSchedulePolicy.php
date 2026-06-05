<?php

namespace App\Policies;

use App\Models\Scheduling\ClassSchedule;
use App\Models\User;

class ClassSchedulePolicy
{
    /**
     * Determine whether the user can list class schedules.
     *
     * Access is granted by either Spatie permission `classes.view` or
     * `schedules.view`. Ownership filtering for teachers and students is
     * applied by schedule queries.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('classes.view') || $user->can('schedules.view');
    }

    /**
     * Determine whether the user can view a class schedule.
     *
     * Admin and staff users can view schedules through the manager role rule.
     * Teachers can view schedules assigned to them, and students can view
     * schedules assigned to them. Unassigned teachers/students are denied.
     */
    public function view(User $user, ClassSchedule $classSchedule): bool
    {
        return $this->managesSchedules($user)
            || ($user->hasRole('teacher') && $classSchedule->teacher_id === $user->id)
            || ($user->hasRole('student') && $classSchedule->student_id === $user->id);
    }

    /**
     * Determine whether the user can create class schedules.
     *
     * Creation requires either `classes.create` or `schedules.create`.
     * Callers without one of those Spatie permissions are denied.
     */
    public function create(User $user): bool
    {
        return $user->can('classes.create') || $user->can('schedules.create');
    }

    /**
     * Determine whether the user can update a class schedule.
     *
     * Users need `classes.update` or `schedules.update`. Admin/staff schedule
     * managers may update any schedule, while teachers may update only their
     * own assigned schedules. Students are denied.
     */
    public function update(User $user, ClassSchedule $classSchedule): bool
    {
        return ($user->can('classes.update') || $user->can('schedules.update'))
            && ($this->managesSchedules($user) || $classSchedule->teacher_id === $user->id);
    }

    /**
     * Determine whether the user can delete a class schedule.
     *
     * Deletion requires admin/staff schedule-manager status plus either
     * `classes.delete` or `schedules.delete`. Teachers and students are denied.
     */
    public function delete(User $user, ClassSchedule $classSchedule): bool
    {
        return ($user->can('classes.delete') || $user->can('schedules.delete')) && $this->managesSchedules($user);
    }

    /**
     * Determine whether the user can cancel a class schedule.
     *
     * Cancellation uses the update rule: update permission is required, and
     * teachers are limited to schedules assigned to them.
     */
    public function cancel(User $user, ClassSchedule $classSchedule): bool
    {
        return $this->update($user, $classSchedule);
    }

    /**
     * Determine whether the user can reschedule a class schedule.
     *
     * Rescheduling uses the update rule: update permission is required, and
     * teachers are limited to schedules assigned to them.
     */
    public function reschedule(User $user, ClassSchedule $classSchedule): bool
    {
        return $this->update($user, $classSchedule);
    }

    /**
     * Determine whether the user is a schedule manager.
     *
     * Only admin and staff roles qualify. Teacher and student roles do not
     * qualify even if they can view or update their own schedules elsewhere.
     */
    private function managesSchedules(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'staff']);
    }
}
