<?php

namespace App\Policies;

use App\Models\ScheduleChangeRequest;
use App\Models\User;

class ScheduleChangeRequestPolicy
{
    /**
     * Determine whether the user can list schedule change requests.
     *
     * Admins can list requests. Staff need the Spatie permission
     * `schedule_change_requests.view`. Students and teachers use their own
     * request endpoints and are denied this admin list rule.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('schedule_change_requests.view'));
    }

    /**
     * Determine whether the user can view a schedule change request.
     *
     * Admins and staff with `schedule_change_requests.view` can view any
     * request. The requester, assigned student, and assigned teacher can view
     * the request tied to them. Other users are denied.
     */
    public function view(User $user, ScheduleChangeRequest $scheduleChangeRequest): bool
    {
        return $this->viewAny($user)
            || (int) $scheduleChangeRequest->requester_id === (int) $user->id
            || ($user->hasRole('student') && (int) $scheduleChangeRequest->student_id === (int) $user->id)
            || ($user->hasRole('teacher') && (int) $scheduleChangeRequest->teacher_id === (int) $user->id);
    }

    /**
     * Determine whether the user can create a schedule change request.
     *
     * Admin and staff roles can create requests. Student and teacher roles
     * need `schedule_change_requests.create`. Users outside these roles, or
     * students/teachers without that permission, are denied.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'staff', 'student', 'teacher'])
            && (
                $user->hasAnyRole(['admin', 'staff'])
                || $user->can('schedule_change_requests.create')
            );
    }

    /**
     * Determine whether the user can review or update a schedule change request.
     *
     * Admins can update requests. Staff need
     * `schedule_change_requests.manage`. Students and teachers are denied
     * update/review access.
     */
    public function update(User $user, ScheduleChangeRequest $scheduleChangeRequest): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('schedule_change_requests.manage'));
    }

    /**
     * Determine whether the user can cancel a schedule change request.
     *
     * Only the original requester can cancel, and only while the request status
     * is pending. Admin/staff roles are not granted cancellation unless they
     * are also the requester.
     */
    public function cancel(User $user, ScheduleChangeRequest $scheduleChangeRequest): bool
    {
        return (int) $scheduleChangeRequest->requester_id === (int) $user->id
            && $scheduleChangeRequest->status === ScheduleChangeRequest::STATUS_PENDING;
    }
}
