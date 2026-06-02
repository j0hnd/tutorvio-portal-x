<?php

namespace App\Policies;

use App\Models\User;

class PayoutReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('payroll.view'));
    }

    public function viewOwnSummary(User $user): bool
    {
        return $user->hasRole('teacher')
            && (bool) config('teacher_earnings.teacher_payroll_visibility_enabled', false);
    }
}
