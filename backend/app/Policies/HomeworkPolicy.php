<?php

namespace App\Policies;

use App\Models\Homework;
use App\Models\User;

class HomeworkPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'teacher', 'student'])
            || ($user->hasRole('staff') && $user->can('homeworks.view'));
    }

    public function view(User $user, Homework $homework): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('homeworks.view'))
            || ($user->hasRole('teacher') && (int) $homework->teacher_id === (int) $user->id)
            || ($user->hasRole('student') && (int) $homework->student_id === (int) $user->id);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'teacher']);
    }

    public function updateProgress(User $user, Homework $homework): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('student') && (int) $homework->student_id === (int) $user->id);
    }

    public function review(User $user, Homework $homework): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('teacher') && (int) $homework->teacher_id === (int) $user->id);
    }
}
