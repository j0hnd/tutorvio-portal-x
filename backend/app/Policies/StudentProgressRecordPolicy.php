<?php

namespace App\Policies;

use App\Models\StudentProgressRecord;
use App\Models\User;

class StudentProgressRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'teacher']);
    }

    public function view(User $user, StudentProgressRecord $studentProgressRecord): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('teacher') && (int) $studentProgressRecord->teacher_id === (int) $user->id);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'teacher']);
    }

    public function update(User $user, StudentProgressRecord $studentProgressRecord): bool
    {
        return $this->view($user, $studentProgressRecord);
    }

    public function delete(User $user, StudentProgressRecord $studentProgressRecord): bool
    {
        return $this->view($user, $studentProgressRecord);
    }
}
