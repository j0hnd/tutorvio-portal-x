<?php

namespace App\Policies;

use App\Models\TeacherChangeRequest;
use App\Models\User;

class TeacherChangeRequestPolicy
{
    /**
     * Determine whether the user can list teacher change requests.
     *
     * Admins can list requests. Staff need the Spatie permission
     * `teacher_change_requests.view`. Students use their own request endpoints
     * and are denied this admin list rule.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('teacher_change_requests.view'));
    }

    /**
     * Determine whether the user can view a teacher change request.
     *
     * Admins and staff with `teacher_change_requests.view` can view any
     * request. Students can view only their own requests. Teachers and
     * unrelated students are denied.
     */
    public function view(User $user, TeacherChangeRequest $teacherChangeRequest): bool
    {
        return $this->viewAny($user)
            || ($user->hasRole('student') && (int) $user->id === (int) $teacherChangeRequest->student_id);
    }

    /**
     * Determine whether the user can create a teacher change request.
     *
     * Only students can create teacher change requests. Admin, staff, and
     * teacher roles are denied by this policy.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('student');
    }

    /**
     * Determine whether the user can review or update a teacher change request.
     *
     * Admins can update requests. Staff need
     * `teacher_change_requests.manage`. Students and teachers are denied.
     */
    public function update(User $user, TeacherChangeRequest $teacherChangeRequest): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('teacher_change_requests.manage'));
    }

    /**
     * Determine whether the user can cancel a teacher change request.
     *
     * Only the student who owns the request can cancel it, and only while the
     * request is pending. Admin/staff roles are not granted cancellation unless
     * they are also the owning student.
     */
    public function cancel(User $user, TeacherChangeRequest $teacherChangeRequest): bool
    {
        return $user->hasRole('student')
            && (int) $user->id === (int) $teacherChangeRequest->student_id
            && $teacherChangeRequest->status === TeacherChangeRequest::STATUS_PENDING;
    }
}
