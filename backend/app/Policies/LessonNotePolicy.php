<?php

namespace App\Policies;

use App\Models\LessonNote;
use App\Models\User;

class LessonNotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'staff', 'teacher', 'student']);
    }

    public function view(User $user, LessonNote $lessonNote): bool
    {
        return $user->hasAnyRole(['admin', 'staff'])
            || ($user->hasRole('teacher') && (int) $lessonNote->teacher_id === (int) $user->id)
            || ($user->hasRole('student') && (int) $lessonNote->student_id === (int) $user->id);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'staff', 'teacher']);
    }

    public function update(User $user, LessonNote $lessonNote): bool
    {
        return $user->hasAnyRole(['admin', 'staff'])
            || ($user->hasRole('teacher') && (int) $lessonNote->teacher_id === (int) $user->id);
    }
}
