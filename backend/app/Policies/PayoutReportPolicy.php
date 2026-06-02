<?php

namespace App\Policies;

use App\Models\User;

class PayoutReportPolicy
{
    /**
     * Determine whether the user can view payout reports.
     *
     * Admins can view payout reports. Staff need `payroll.view`.
     * Teachers and students are denied this admin report view.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('payroll.view'));
    }

    /**
     * Determine whether the user can view their own payout summary.
     *
     * Only teachers can view their own summary, and only when
     * `teacher_earnings.teacher_payroll_visibility_enabled` is enabled.
     * Admin/staff report access does not grant this teacher self-service gate.
     */
    public function viewOwnSummary(User $user): bool
    {
        return $user->hasRole('teacher')
            && (bool) config('teacher_earnings.teacher_payroll_visibility_enabled', false);
    }
}
