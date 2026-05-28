<?php

namespace App\Policies;

use App\Models\ScheduleChangeRequest;
use App\Models\User;

class ScheduleChangeRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('schedule_change_requests.view'));
    }

    public function view(User $user, ScheduleChangeRequest $scheduleChangeRequest): bool
    {
        return $this->viewAny($user)
            || (int) $scheduleChangeRequest->requester_id === (int) $user->id
            || ($user->hasRole('student') && (int) $scheduleChangeRequest->student_id === (int) $user->id)
            || ($user->hasRole('teacher') && (int) $scheduleChangeRequest->teacher_id === (int) $user->id);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'staff', 'student', 'teacher'])
            && (
                $user->hasAnyRole(['admin', 'staff'])
                || $user->can('schedule_change_requests.create')
            );
    }

    public function update(User $user, ScheduleChangeRequest $scheduleChangeRequest): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('schedule_change_requests.manage'));
    }

    public function cancel(User $user, ScheduleChangeRequest $scheduleChangeRequest): bool
    {
        return (int) $scheduleChangeRequest->requester_id === (int) $user->id
            && $scheduleChangeRequest->status === ScheduleChangeRequest::STATUS_PENDING;
    }
}
