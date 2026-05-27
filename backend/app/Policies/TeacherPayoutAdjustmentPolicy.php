<?php

namespace App\Policies;

use App\Models\TeacherPayoutAdjustment;
use App\Models\User;

class TeacherPayoutAdjustmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('payroll.view'));
    }

    public function view(User $user, TeacherPayoutAdjustment $teacherPayoutAdjustment): bool
    {
        if ($this->viewAny($user)) {
            return true;
        }

        return $this->viewOwnSummary($user)
            && (int) $teacherPayoutAdjustment->teacher_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('payroll.manage'));
    }

    public function viewOwnSummary(User $user): bool
    {
        return $user->hasRole('teacher')
            && (bool) config('teacher_earnings.teacher_payroll_visibility_enabled', false);
    }
}
