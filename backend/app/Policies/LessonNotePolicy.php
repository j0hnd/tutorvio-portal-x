<?php

namespace App\Policies;

use App\Models\LessonNote;
use App\Models\User;

class LessonNotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'teacher', 'student'])
            || ($user->hasRole('staff') && $user->can('lesson_notes.view'));
    }

    public function view(User $user, LessonNote $lessonNote): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('lesson_notes.view'))
            || ($user->hasRole('teacher') && (int) $lessonNote->teacher_id === (int) $user->id)
            || ($user->hasRole('student') && (int) $lessonNote->student_id === (int) $user->id);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'teacher'])
            || ($user->hasRole('staff') && $user->can('lesson_notes.create'));
    }

    public function update(User $user, LessonNote $lessonNote): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('lesson_notes.update'))
            || ($user->hasRole('teacher') && (int) $lessonNote->teacher_id === (int) $user->id);
    }
}
