<?php

namespace App\Policies;

use App\Models\PayoutPeriod;
use App\Models\User;

class PayoutPeriodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('payout_periods.view'));
    }

    public function view(User $user, PayoutPeriod $payoutPeriod): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('payout_periods.manage'));
    }

    public function update(User $user, PayoutPeriod $payoutPeriod): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('payout_periods.manage'));
    }
}
