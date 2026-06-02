<?php

namespace App\Policies;

use App\Models\StudentProgressRecord;
use App\Models\User;

class StudentProgressRecordPolicy
{
    /**
     * Determine whether the user can list student progress records.
     *
     * Admins, teachers, and students can list records. Staff need
     * `student_progress_records.view`. Row-level assignment and ownership
     * filtering is applied by the controller/query layer.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'teacher', 'student'])
            || ($user->hasRole('staff') && $user->can('student_progress_records.view'));
    }

    /**
     * Determine whether the user can view a student progress record.
     *
     * Admins can view any record. Staff need `student_progress_records.view`.
     * Teachers can view records for students assigned to them. Students can
     * view their own records. Others are denied.
     */
    public function view(User $user, StudentProgressRecord $studentProgressRecord): bool
    {
        $studentProgressRecord->loadMissing('student.studentProfile');

        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('student_progress_records.view'))
            || ($user->hasRole('teacher') && (int) $studentProgressRecord->student?->studentProfile?->assigned_teacher_id === (int) $user->id)
            || ($user->hasRole('student') && (int) $studentProgressRecord->student_id === (int) $user->id);
    }

    /**
     * Determine whether the user can create a student progress record.
     *
     * Admins and teachers can create records. Staff need
     * `student_progress_records.create`. Students are denied.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->hasRole('teacher')
            || ($user->hasRole('staff') && $user->can('student_progress_records.create'));
    }

    /**
     * Determine whether the user can update a student progress record.
     *
     * Admins can update any record. Staff need `student_progress_records.update`.
     * Teachers can update records for students assigned to them. Students are
     * denied update access.
     */
    public function update(User $user, StudentProgressRecord $studentProgressRecord): bool
    {
        $studentProgressRecord->loadMissing('student.studentProfile');

        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('student_progress_records.update'))
            || ($user->hasRole('teacher') && (int) $studentProgressRecord->student?->studentProfile?->assigned_teacher_id === (int) $user->id);
    }

    /**
     * Determine whether the user can delete a student progress record.
     *
     * Admins can delete records. Staff need `student_progress_records.delete`.
     * Teachers and students are denied.
     */
    public function delete(User $user, StudentProgressRecord $studentProgressRecord): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('student_progress_records.delete'));
    }
}
