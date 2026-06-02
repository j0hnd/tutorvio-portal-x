<?php

namespace App\Policies;

use App\Models\Homework;
use App\Models\User;

class HomeworkPolicy
{
    /**
     * Determine whether the user can list homework records.
     *
     * Admins, teachers, and students can list homework. Staff need the Spatie
     * permission `homeworks.view`. Row-level teacher/student filtering is
     * applied by the homework query layer.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'teacher', 'student'])
            || ($user->hasRole('staff') && $user->can('homeworks.view'));
    }

    /**
     * Determine whether the user can view a homework record.
     *
     * Admins can view all homework. Staff need `homeworks.view`. Teachers can
     * view homework assigned to them, and students can view their own homework.
     * Unassigned teachers and unrelated students are denied.
     */
    public function view(User $user, Homework $homework): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('homeworks.view'))
            || ($user->hasRole('teacher') && (int) $homework->teacher_id === (int) $user->id)
            || ($user->hasRole('student') && (int) $homework->student_id === (int) $user->id);
    }

    /**
     * Determine whether the user can create homework.
     *
     * Admins and teachers can create homework. Staff and students are denied
     * by this policy; no Spatie permission grants staff creation here.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'teacher']);
    }

    /**
     * Determine whether the user can update homework progress.
     *
     * Admins can update progress for any homework. Students can update progress
     * only on homework assigned to them. Teachers and unrelated students are
     * denied.
     */
    public function updateProgress(User $user, Homework $homework): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('student') && (int) $homework->student_id === (int) $user->id);
    }

    /**
     * Determine whether the user can review homework.
     *
     * Admins can review any homework. Teachers can review homework assigned to
     * them. Students and unassigned teachers are denied.
     */
    public function review(User $user, Homework $homework): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('teacher') && (int) $homework->teacher_id === (int) $user->id);
    }
}
