<?php

namespace App\Policies;

use App\Models\Subscription;
use App\Models\User;

class SubscriptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('subscriptions.view'));
    }

    public function view(User $user, Subscription $subscription): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('subscriptions.create'));
    }

    public function update(User $user, Subscription $subscription): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('subscriptions.update'));
    }

    public function updatePaymentStatus(User $user, Subscription $subscription): bool
    {
        return $this->update($user, $subscription);
    }

    public function renew(User $user, Subscription $subscription): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('subscriptions.create'));
    }

    public function cancel(User $user, Subscription $subscription): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('subscriptions.delete'));
    }
}
