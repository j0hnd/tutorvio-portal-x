<?php

namespace App\Policies;

use App\Models\LessonNote;
use App\Models\User;

class LessonNotePolicy
{
    /**
     * Determine whether the user can list lesson notes.
     *
     * Admins, teachers, and students can list notes. Staff need
     * `lesson_notes.view`. Row-level teacher/student filtering is applied by
     * the lesson-note query layer.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'teacher', 'student'])
            || ($user->hasRole('staff') && $user->can('lesson_notes.view'));
    }

    /**
     * Determine whether the user can view a lesson note.
     *
     * Admins can view any note. Staff need `lesson_notes.view`. Teachers and
     * students can view notes assigned to their own user id. Unassigned users
     * and unrelated students are denied.
     */
    public function view(User $user, LessonNote $lessonNote): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('lesson_notes.view'))
            || ($user->hasRole('teacher') && (int) $lessonNote->teacher_id === (int) $user->id)
            || ($user->hasRole('student') && (int) $lessonNote->student_id === (int) $user->id);
    }

    /**
     * Determine whether the user can create lesson notes.
     *
     * Admins and teachers can create notes. Staff need `lesson_notes.create`.
     * Students are denied.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'teacher'])
            || ($user->hasRole('staff') && $user->can('lesson_notes.create'));
    }

    /**
     * Determine whether the user can update a lesson note.
     *
     * Admins can update any note. Staff need `lesson_notes.update`. Teachers
     * can update notes assigned to them. Students and unrelated teachers are
     * denied.
     */
    public function update(User $user, LessonNote $lessonNote): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('lesson_notes.update'))
            || ($user->hasRole('teacher') && (int) $lessonNote->teacher_id === (int) $user->id);
    }
}
