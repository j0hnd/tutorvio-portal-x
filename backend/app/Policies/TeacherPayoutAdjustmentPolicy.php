<?php

namespace App\Policies;

use App\Models\TeacherPayoutAdjustment;
use App\Models\User;

class TeacherPayoutAdjustmentPolicy
{
    /**
     * Determine whether the user can list teacher payout adjustments.
     *
     * Admins can list adjustments. Staff need the Spatie permission
     * `payroll.view`. Teachers use the own-summary rule and are denied this
     * admin list rule.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('payroll.view'));
    }

    /**
     * Determine whether the user can view a payout adjustment.
     *
     * Admins and staff with `payroll.view` can view any adjustment. Teachers
     * must pass the own-summary rule and own the adjustment. Others are denied.
     */
    public function view(User $user, TeacherPayoutAdjustment $teacherPayoutAdjustment): bool
    {
        if ($this->viewAny($user)) {
            return true;
        }

        return $this->viewOwnSummary($user)
            && (int) $teacherPayoutAdjustment->teacher_id === (int) $user->id;
    }

    /**
     * Determine whether the user can create a payout adjustment.
     *
     * Admins can create adjustments. Staff need `payroll.manage`. Teachers and
     * students are denied.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('payroll.manage'));
    }

    /**
     * Determine whether the teacher can view their own payout summary.
     *
     * Only teachers are eligible, and only when
     * `teacher_earnings.teacher_payroll_visibility_enabled` is enabled.
     */
    public function viewOwnSummary(User $user): bool
    {
        return $user->hasRole('teacher')
            && (bool) config('teacher_earnings.teacher_payroll_visibility_enabled', false);
    }
}
