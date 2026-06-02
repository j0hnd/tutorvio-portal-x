<?php

namespace App\Policies;

use App\Models\TeacherChangeRequest;
use App\Models\User;

class TeacherChangeRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('teacher_change_requests.view'));
    }

    public function view(User $user, TeacherChangeRequest $teacherChangeRequest): bool
    {
        return $this->viewAny($user)
            || ($user->hasRole('student') && (int) $user->id === (int) $teacherChangeRequest->student_id);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('student');
    }

    public function update(User $user, TeacherChangeRequest $teacherChangeRequest): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('teacher_change_requests.manage'));
    }

    public function cancel(User $user, TeacherChangeRequest $teacherChangeRequest): bool
    {
        return $user->hasRole('student')
            && (int) $user->id === (int) $teacherChangeRequest->student_id
            && $teacherChangeRequest->status === TeacherChangeRequest::STATUS_PENDING;
    }
}
