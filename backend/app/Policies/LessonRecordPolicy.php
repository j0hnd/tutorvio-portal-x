<?php

namespace App\Policies;

use App\Models\LessonRecord;
use App\Models\User;

class LessonRecordPolicy
{
    /**
     * Determine whether the user can list lesson records.
     *
     * Admins, teachers, and students can list records. Staff need
     * `lesson_records.view`. Row-level teacher/student filtering is applied by
     * the lesson-record query layer.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'teacher', 'student'])
            || ($user->hasRole('staff') && $user->can('lesson_records.view'));
    }

    /**
     * Determine whether the user can view a lesson record.
     *
     * Admins can view any record. Staff need `lesson_records.view`. Teachers
     * can view records assigned to them, and students can view their own
     * records. Unassigned teachers and unrelated students are denied.
     */
    public function view(User $user, LessonRecord $lessonRecord): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('lesson_records.view'))
            || ($user->hasRole('teacher') && $lessonRecord->teacher_id === $user->id)
            || ($user->hasRole('student') && $lessonRecord->student_id === $user->id);
    }

    /**
     * Determine whether the user can create a lesson record.
     *
     * Admins can create records. Staff need `lesson_records.create`.
     * Teachers and students are denied by this policy.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('lesson_records.create'));
    }

    /**
     * Determine whether the user can update a lesson record.
     *
     * Admins can update records. Staff need `lesson_records.update`.
     * Teachers and students are denied even for assigned or owned records.
     */
    public function update(User $user, LessonRecord $lessonRecord): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('lesson_records.update'));
    }

    /**
     * Determine whether the user can delete a lesson record.
     *
     * Admins can delete records. Staff need `lesson_records.delete`.
     * Teachers and students are denied.
     */
    public function delete(User $user, LessonRecord $lessonRecord): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('lesson_records.delete'));
    }

    /**
     * Determine whether the user can cancel a lesson record.
     *
     * Cancellation uses the update rule: admins are allowed and staff require
     * `lesson_records.update`; teachers and students are denied.
     */
    public function cancel(User $user, LessonRecord $lessonRecord): bool
    {
        return $this->update($user, $lessonRecord);
    }
}
