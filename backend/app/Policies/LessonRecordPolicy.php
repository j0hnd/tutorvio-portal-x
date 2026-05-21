<?php

namespace App\Policies;

use App\Models\LessonRecord;
use App\Models\User;

class LessonRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('lesson_records.view') || $user->can('classes.view');
    }

    public function view(User $user, LessonRecord $lessonRecord): bool
    {
        return $this->managesLessonRecords($user)
            || ($user->hasRole('teacher') && $lessonRecord->teacher_id === $user->id)
            || ($user->hasRole('student') && $lessonRecord->student_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $this->managesLessonRecords($user)
            && ($user->can('lesson_records.create') || $user->can('classes.create'));
    }

    public function update(User $user, LessonRecord $lessonRecord): bool
    {
        if ($this->managesLessonRecords($user)) {
            return $user->can('lesson_records.update') || $user->can('classes.update');
        }

        return $user->hasRole('teacher')
            && $lessonRecord->teacher_id === $user->id
            && ($user->can('lesson_records.update') || $user->can('classes.update'));
    }

    public function delete(User $user, LessonRecord $lessonRecord): bool
    {
        return $this->managesLessonRecords($user)
            && ($user->can('lesson_records.delete') || $user->can('classes.delete'));
    }

    public function cancel(User $user, LessonRecord $lessonRecord): bool
    {
        return $this->update($user, $lessonRecord);
    }

    private function managesLessonRecords(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'staff']);
    }
}
