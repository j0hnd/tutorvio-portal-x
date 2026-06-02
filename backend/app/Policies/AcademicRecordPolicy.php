<?php

namespace App\Policies;

use App\Models\AcademicRecord;
use App\Models\User;

class AcademicRecordPolicy
{
    /**
     * Determine whether the user can list academic records.
     *
     * Admins and students may list records. Teachers and staff must have the
     * Spatie permission `academic_records.view`. Row-level ownership and
     * assignment filtering is applied by the query/controller layer.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'student'])
            || ($user->hasRole('teacher') && $user->can('academic_records.view'))
            || ($user->hasRole('staff') && $user->can('academic_records.view'));
    }

    /**
     * Determine whether the user can view a specific academic record.
     *
     * Admins can view any record. Staff need `academic_records.view`.
     * Teachers need `academic_records.view` and must be assigned to the
     * record's student. Students can only view their own records. Archived
     * records are denied to students and teachers even when ownership matches.
     */
    public function view(User $user, AcademicRecord $academicRecord): bool
    {
        $academicRecord->loadMissing('student.studentProfile');

        if ($academicRecord->status === AcademicRecord::STATUS_ARCHIVED) {
            return $user->hasRole('admin')
                || ($user->hasRole('staff') && $user->can('academic_records.view'));
        }

        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('academic_records.view'))
            || ($user->hasRole('teacher')
                && $user->can('academic_records.view')
                && (int) $academicRecord->student?->studentProfile?->assigned_teacher_id === (int) $user->id)
            || ($user->hasRole('student') && (int) $academicRecord->student_id === (int) $user->id);
    }

    /**
     * Determine whether the user can create an academic record.
     *
     * Admins can create records. Staff need the Spatie permission
     * `academic_records.manage`. Teachers and students are denied.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('academic_records.manage'));
    }

    /**
     * Determine whether the user can update an academic record.
     *
     * Admins can update any record. Staff need `academic_records.manage`.
     * Teachers and students are denied, including for assigned or owned records.
     */
    public function update(User $user, AcademicRecord $academicRecord): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('academic_records.manage'));
    }

    /**
     * Determine whether the user can archive an academic record.
     *
     * Archiving follows the same rule as updating: admin users are allowed and
     * staff require `academic_records.manage`; all other roles are denied.
     */
    public function archive(User $user, AcademicRecord $academicRecord): bool
    {
        return $this->update($user, $academicRecord);
    }

    /**
     * Determine whether the user can view internal academic-record notes.
     *
     * Internal notes are limited to admins and staff with
     * `academic_records.manage`. Students and teachers are denied even when
     * they can view the public academic record.
     */
    public function viewInternalNotes(User $user, AcademicRecord $academicRecord): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('academic_records.manage'));
    }
}
