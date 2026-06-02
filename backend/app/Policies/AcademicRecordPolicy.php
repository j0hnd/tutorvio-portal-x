<?php

namespace App\Policies;

use App\Models\AcademicRecord;
use App\Models\User;

class AcademicRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'student'])
            || ($user->hasRole('teacher') && $user->can('academic_records.view'))
            || ($user->hasRole('staff') && $user->can('academic_records.view'));
    }

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

    public function create(User $user): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('academic_records.manage'));
    }

    public function update(User $user, AcademicRecord $academicRecord): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('academic_records.manage'));
    }

    public function archive(User $user, AcademicRecord $academicRecord): bool
    {
        return $this->update($user, $academicRecord);
    }

    public function viewInternalNotes(User $user, AcademicRecord $academicRecord): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('academic_records.manage'));
    }
}
