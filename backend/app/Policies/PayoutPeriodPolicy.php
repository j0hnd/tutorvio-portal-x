<?php

namespace App\Policies;

use App\Models\PayoutPeriod;
use App\Models\User;

class PayoutPeriodPolicy
{
    /**
     * Determine whether the user can list payout periods.
     *
     * Admins can list payout periods. Staff need the Spatie permission
     * `payout_periods.view`. Teachers and students are denied.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('payout_periods.view'));
    }

    /**
     * Determine whether the user can view a payout period.
     *
     * Detail access follows the list rule: admins are allowed and staff need
     * `payout_periods.view`. No ownership exception applies.
     */
    public function view(User $user, PayoutPeriod $payoutPeriod): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can create a payout period.
     *
     * Admins can create periods. Staff need `payout_periods.manage`.
     * Teachers and students are denied.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('payout_periods.manage'));
    }

    /**
     * Determine whether the user can update a payout period.
     *
     * Admins can update periods. Staff need `payout_periods.manage`.
     * Teachers and students are denied.
     */
    public function update(User $user, PayoutPeriod $payoutPeriod): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('payout_periods.manage'));
    }
}
