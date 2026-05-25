<?php

namespace App\Policies;

use App\Models\StudentProgressRecord;
use App\Models\User;

class StudentProgressRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'teacher', 'student'])
            || ($user->hasRole('staff') && $user->can('student_progress_records.view'));
    }

    public function view(User $user, StudentProgressRecord $studentProgressRecord): bool
    {
        $studentProgressRecord->loadMissing('student.studentProfile');

        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('student_progress_records.view'))
            || ($user->hasRole('teacher') && (int) $studentProgressRecord->student?->studentProfile?->assigned_teacher_id === (int) $user->id)
            || ($user->hasRole('student') && (int) $studentProgressRecord->student_id === (int) $user->id);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->hasRole('teacher')
            || ($user->hasRole('staff') && $user->can('student_progress_records.create'));
    }

    public function update(User $user, StudentProgressRecord $studentProgressRecord): bool
    {
        $studentProgressRecord->loadMissing('student.studentProfile');

        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('student_progress_records.update'))
            || ($user->hasRole('teacher') && (int) $studentProgressRecord->student?->studentProfile?->assigned_teacher_id === (int) $user->id);
    }

    public function delete(User $user, StudentProgressRecord $studentProgressRecord): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('student_progress_records.delete'));
    }
}
