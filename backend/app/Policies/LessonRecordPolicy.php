<?php

namespace App\Policies;

use App\Models\LessonRecord;
use App\Models\User;

class LessonRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'teacher', 'student'])
            || ($user->hasRole('staff') && $user->can('lesson_records.view'));
    }

    public function view(User $user, LessonRecord $lessonRecord): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('lesson_records.view'))
            || ($user->hasRole('teacher') && $lessonRecord->teacher_id === $user->id)
            || ($user->hasRole('student') && $lessonRecord->student_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('lesson_records.create'));
    }

    public function update(User $user, LessonRecord $lessonRecord): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('lesson_records.update'));
    }

    public function delete(User $user, LessonRecord $lessonRecord): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('lesson_records.delete'));
    }

    public function cancel(User $user, LessonRecord $lessonRecord): bool
    {
        return $this->update($user, $lessonRecord);
    }
}
